<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Aspects;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use Aftandilmmd\Cacheable\Contracts\KeyResolver;
use Aftandilmmd\Cacheable\Events\CacheForgotten;
use Aftandilmmd\Cacheable\Events\CacheHit;
use Aftandilmmd\Cacheable\Events\CacheMissed;
use Aftandilmmd\Cacheable\Events\CacheWritten;
use Aftandilmmd\Cacheable\Exceptions\LockTimeoutException;
use Aftandilmmd\Cacheable\Support\CacheableConfig;
use Aftandilmmd\Cacheable\Support\RefreshAheadJob;
use ReflectionMethod;
use Throwable;

class CacheableAspect implements CacheAspect
{
    public function __construct(
        protected readonly CacheFactory $cache,
        protected readonly KeyResolver $keyResolver,
        protected readonly EventDispatcher $events,
        protected readonly ?QueueContract $queue = null,
        /** @var array<string, mixed> */
        protected readonly array $config = [],
    ) {}

    public function handle(
        object $instance,
        string $method,
        array $args,
        Closure $callback,
    ): mixed {
        if (! ($this->config['enabled'] ?? true)) {
            return $callback();
        }

        $reflection = new ReflectionMethod($instance, $method);
        $attribute = $this->extractAttribute($reflection);

        if ($attribute === null) {
            return $callback();
        }

        $cfg = CacheableConfig::fromAttribute($attribute, $this->config);

        // when / unless gating
        if (! $this->shouldCache($instance, $attribute, $args)) {
            $result = $callback();
            $this->handleInvalidation($instance, $reflection, $attribute, $cfg, $args);

            return $result;
        }

        $key = $this->keyResolver->resolve($attribute, $instance::class, $reflection, $args);
        $repo = $this->repository($cfg);

        $produce = function () use ($callback, $instance, $reflection, $attribute, $cfg, $args) {
            $result = $callback();
            $this->handleInvalidation($instance, $reflection, $attribute, $cfg, $args);

            return $result;
        };

        if ($cfg->lock) {
            return $this->withLock($repo, $key, $instance::class, $method, $cfg, $produce);
        }

        if ($cfg->refreshAhead > 0 && $cfg->ttl !== null && $cfg->ttl > 0) {
            return $this->withRefreshAhead($repo, $key, $instance::class, $method, $cfg, $produce);
        }

        return $this->remember($repo, $key, $instance::class, $method, $cfg, $produce);
    }

    protected function extractAttribute(ReflectionMethod $method): ?Cacheable
    {
        $attrs = $method->getAttributes(Cacheable::class);

        return $attrs === [] ? null : $attrs[0]->newInstance();
    }

    /**
     * @param  array<int|string, mixed>  $args
     */
    protected function shouldCache(object $instance, Cacheable $attr, array $args): bool
    {
        if ($attr->when !== null && method_exists($instance, $attr->when)) {
            if (! (bool) $instance->{$attr->when}(...array_values($args))) {
                return false;
            }
        }

        if ($attr->unless !== null && method_exists($instance, $attr->unless)) {
            if ((bool) $instance->{$attr->unless}(...array_values($args))) {
                return false;
            }
        }

        return true;
    }

    protected function repository(CacheableConfig $cfg): CacheRepository
    {
        $store = $cfg->store ?? ($this->config['store'] ?? null);
        $repo = $store ? $this->cache->store($store) : $this->cache->store();

        if ($cfg->tags !== [] && $this->supportsTags($repo)) {
            /** @var CacheRepository $tagged */
            $tagged = $repo->tags($cfg->tags);

            return $tagged;
        }

        return $repo;
    }

    protected function supportsTags(CacheRepository $repo): bool
    {
        return method_exists($repo->getStore(), 'tags');
    }

    /**
     * @param  Closure(): mixed  $produce
     */
    protected function remember(
        CacheRepository $repo,
        string $key,
        string $class,
        string $method,
        CacheableConfig $cfg,
        Closure $produce,
    ): mixed {
        if ($repo->has($key)) {
            $value = $repo->get($key);
            $this->dispatch(new CacheHit($key, $class, $method, $value));

            return $value;
        }

        $this->dispatch(new CacheMissed($key, $class, $method));
        $value = $produce();

        if (! $this->shouldStore($value, $cfg)) {
            return $value;
        }

        $ttl = $this->resolveTtl($cfg);

        if ($ttl === null) {
            $repo->forever($key, $value);
        } else {
            $repo->put($key, $value, $ttl);
            $this->writeMeta($repo, $key, $ttl, $cfg);
        }

        $this->dispatch(new CacheWritten($key, $class, $method, $value, $ttl));

        return $value;
    }

    /**
     * @param  Closure(): mixed  $produce
     */
    protected function withLock(
        CacheRepository $repo,
        string $key,
        string $class,
        string $method,
        CacheableConfig $cfg,
        Closure $produce,
    ): mixed {
        if ($repo->has($key)) {
            $value = $repo->get($key);
            $this->dispatch(new CacheHit($key, $class, $method, $value));

            return $value;
        }

        $store = $repo->getStore();
        if (! method_exists($store, 'lock')) {
            return $this->remember($repo, $key, $class, $method, $cfg, $produce);
        }

        $lockPrefix = (string) ($this->config['stampede']['lock_prefix'] ?? 'cacheable:lock:');
        $lock = $store->lock($lockPrefix.$key, $cfg->lockWait);

        try {
            $acquired = $lock->block($cfg->lockWait);

            if (! $acquired) {
                throw new LockTimeoutException(
                    "Could not acquire cacheable lock for key [{$key}] within {$cfg->lockWait}s.",
                );
            }

            // Double-check after acquiring
            if ($repo->has($key)) {
                $value = $repo->get($key);
                $this->dispatch(new CacheHit($key, $class, $method, $value));

                return $value;
            }

            return $this->remember($repo, $key, $class, $method, $cfg, $produce);
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // Lock may have been released already, ignore.
            }
        }
    }

    /**
     * @param  Closure(): mixed  $produce
     */
    protected function withRefreshAhead(
        CacheRepository $repo,
        string $key,
        string $class,
        string $method,
        CacheableConfig $cfg,
        Closure $produce,
    ): mixed {
        $metaKey = $key.':__meta';

        if (! $repo->has($key)) {
            return $this->remember($repo, $key, $class, $method, $cfg, $produce);
        }

        $value = $repo->get($key);
        $this->dispatch(new CacheHit($key, $class, $method, $value));

        $meta = $repo->get($metaKey);
        if (! is_array($meta) || ! isset($meta['expires_at'])) {
            return $value;
        }

        $remaining = (int) $meta['expires_at'] - time();
        $threshold = (int) ((int) $cfg->ttl * $cfg->refreshAhead);

        if ($remaining > 0 && $remaining <= $threshold) {
            // Within stale window: refresh either sync or async
            if ($cfg->swrAsync && $this->queue !== null) {
                $this->dispatchRefresh($key, $class, $method, $cfg);
            } else {
                // Inline refresh - keep request fast by *not* re-invoking on this request
                // unless this is the only way. We refresh here synchronously and return
                // the *fresh* value so the next caller already sees it cached.
                $fresh = $produce();
                if ($this->shouldStore($fresh, $cfg)) {
                    $ttl = $this->resolveTtl($cfg);
                    if ($ttl === null) {
                        $repo->forever($key, $fresh);
                    } else {
                        $repo->put($key, $fresh, $ttl);
                        $this->writeMeta($repo, $key, $ttl, $cfg);
                    }
                    $this->dispatch(new CacheWritten($key, $class, $method, $fresh, $ttl));

                    return $fresh;
                }
            }
        }

        return $value;
    }

    protected function dispatchRefresh(string $key, string $class, string $method, CacheableConfig $cfg): void
    {
        if ($this->queue === null) {
            return;
        }

        $job = new RefreshAheadJob($class, $method, $key);

        if ($cfg->swrQueueConnection !== null) {
            $job->onConnection($cfg->swrQueueConnection);
        }
        $job->onQueue($cfg->swrQueueName);

        $this->queue->push($job);
    }

    protected function resolveTtl(CacheableConfig $cfg): ?int
    {
        if ($cfg->ttl === null) {
            return null;
        }

        $ttl = $cfg->ttl;
        if ($cfg->jitter > 0) {
            $ttl += random_int(0, $cfg->jitter);
        }

        return $ttl;
    }

    protected function writeMeta(CacheRepository $repo, string $key, int $ttl, CacheableConfig $cfg): void
    {
        if ($cfg->refreshAhead <= 0) {
            return;
        }

        $repo->put($key.':__meta', [
            'cached_at' => time(),
            'expires_at' => time() + $ttl,
        ], $ttl);
    }

    protected function shouldStore(mixed $value, CacheableConfig $cfg): bool
    {
        if ($value === null && ! $cfg->cacheNull) {
            return false;
        }

        if (! $cfg->cacheEmpty) {
            if (is_array($value) && $value === []) {
                return false;
            }
            if (is_string($value) && $value === '') {
                return false;
            }
            if (is_object($value) && method_exists($value, 'isEmpty') && (bool) $value->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle forget / forgetTags directives. Errors here must never break
     * the caller, so each operation is best-effort.
     *
     * @param  array<int|string, mixed>  $args
     */
    protected function handleInvalidation(
        object $instance,
        ReflectionMethod $method,
        Cacheable $attr,
        CacheableConfig $cfg,
        array $args,
    ): void {
        if ($attr->forget === [] && $attr->forgetTags === []) {
            return;
        }

        $store = $cfg->store ?? ($this->config['store'] ?? null);
        $repo = $store ? $this->cache->store($store) : $this->cache->store();

        $forgottenKeys = [];

        foreach ($attr->forget as $template) {
            $synthetic = new Cacheable(
                key: $template,
                prefix: $cfg->prefix,
                version: $cfg->version,
            );

            try {
                $key = $this->keyResolver->resolve($synthetic, $instance::class, $method, $args);
                $repo->forget($key);
                $repo->forget($key.':__meta');
                $forgottenKeys[] = $key;
            } catch (Throwable) {
                // Skip malformed templates rather than crashing.
            }
        }

        if ($attr->forgetTags !== [] && $this->supportsTags($repo)) {
            try {
                $repo->tags($attr->forgetTags)->flush();
            } catch (Throwable) {
                // Best-effort.
            }
        }

        $this->dispatch(new CacheForgotten(
            $instance::class,
            $method->getName(),
            $forgottenKeys,
            $attr->forgetTags,
        ));
    }

    protected function dispatch(object $event): void
    {
        if (! ($this->config['events']['enabled'] ?? true)) {
            return;
        }

        $this->events->dispatch($event);
    }
}
