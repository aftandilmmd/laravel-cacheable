<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use Aftandilmmd\Cacheable\Contracts\KeyResolver;
use Aftandilmmd\Cacheable\Support\CacheableProxy;
use ReflectionMethod;

/**
 * Public-facing API for the package. Exposed through the `Cacheable` facade.
 *
 * Typical usage:
 *   - Cacheable::proxy($service)                          — wrap in transparent proxy
 *   - Cacheable::forget(UserService::class, 'find', [42]) — invalidate one entry
 *   - Cacheable::flushTags(['users'])                     — flush by tag
 *   - Cacheable::keyFor(UserService::class, 'find', [42]) — inspect generated key
 */
class CacheableManager
{
    public function __construct(
        protected readonly CacheAspect $aspect,
        protected readonly KeyResolver $keyResolver,
        protected readonly CacheFactory $cache,
        protected readonly Container $container,
        /** @var array<string, mixed> */
        protected readonly array $config = [],
    ) {}

    /**
     * Wrap an object so method calls run through the cache aspect.
     *
     * @template T of object
     *
     * @param  T  $instance
     * @return CacheableProxy<T>
     */
    public function proxy(object $instance): CacheableProxy
    {
        return CacheableProxy::wrap($instance, $this->aspect);
    }

    /**
     * Invoke any closure as if it were a Cacheable method. Useful for
     * one-off cached blocks.
     *
     * @param  string   $owner      Logical owner label embedded in the cache key (e.g. class or module name).
     * @param  array<int|string, mixed>  $args
     * @param  Closure(): mixed  $callback
     */
    public function call(
        Cacheable $attribute,
        string $owner,
        string $methodName,
        array $args,
        Closure $callback,
    ): mixed {
        // We materialize a tiny anonymous instance carrying the attribute
        // so the existing aspect path works unchanged.
        $instance = new class($attribute, $methodName, $callback)
        {
            public function __construct(
                public Cacheable $__attr,
                public string $__method,
                public Closure $__callback,
            ) {}

            public function __invoke(): mixed
            {
                return ($this->__callback)();
            }
        };

        return $this->aspect->handle(
            instance: $instance,
            method: '__invoke',
            args: $args,
            callback: $callback,
        );
    }

    /**
     * Forget the cache entry for a specific method invocation.
     *
     * @param  array<int|string, mixed>  $args
     */
    public function forget(string $class, string $method, array $args = []): bool
    {
        $reflection = new ReflectionMethod($class, $method);
        $attrs = $reflection->getAttributes(Cacheable::class);

        if ($attrs === []) {
            return false;
        }

        $attribute = $attrs[0]->newInstance();
        $key = $this->keyResolver->resolve($attribute, $class, $reflection, $args);

        $store = $attribute->store ?? ($this->config['store'] ?? null);
        $repo = $store ? $this->cache->store($store) : $this->cache->store();

        if ($attribute->tags !== [] && method_exists($repo->getStore(), 'tags')) {
            $repo = $repo->tags($attribute->tags);
        }

        $repo->forget($key.':__meta');

        return $repo->forget($key);
    }

    /**
     * Flush cache entries by tag (taggable stores only).
     *
     * @param  list<string>  $tags
     */
    public function flushTags(array $tags, ?string $store = null): bool
    {
        $repo = $store ? $this->cache->store($store) : $this->cache->store();

        if (! method_exists($repo->getStore(), 'tags')) {
            return false;
        }

        $repo->tags($tags)->flush();

        return true;
    }

    /**
     * Forget the cache entry for a specific method invocation and immediately
     * re-execute the method to warm the cache with a fresh value.
     *
     * @param  array<int|string, mixed>  $args
     */
    public function refresh(string $class, string $method, array $args = []): mixed
    {
        $this->forget($class, $method, $args);

        $instance = $this->container->make($class);

        return $this->aspect->handle(
            instance: $instance,
            method: $method,
            args: $args,
            callback: fn () => $instance->{$method}(...$args),
        );
    }

    /**
     * Resolve the cache key that would be produced for a given invocation
     * without actually caching anything. Handy for debugging.
     *
     * @param  array<int|string, mixed>  $args
     */
    public function keyFor(string $class, string $method, array $args = []): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $attrs = $reflection->getAttributes(Cacheable::class);
        $attribute = $attrs === [] ? new Cacheable : $attrs[0]->newInstance();

        return $this->keyResolver->resolve($attribute, $class, $reflection, $args);
    }
}
