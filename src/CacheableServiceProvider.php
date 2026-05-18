<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\ServiceProvider;
use Aftandilmmd\Cacheable\Aspects\CacheableAspect;
use Aftandilmmd\Cacheable\Contracts\ArgumentNormalizer;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use Aftandilmmd\Cacheable\Contracts\KeyResolver;
use Aftandilmmd\Cacheable\Resolvers\DefaultKeyResolver;
use Aftandilmmd\Cacheable\Support\CacheableProxy;
use Aftandilmmd\Cacheable\Support\DefaultArgumentNormalizer;

class CacheableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cacheable.php', 'cacheable');

        $this->app->singleton(ArgumentNormalizer::class, DefaultArgumentNormalizer::class);

        $this->app->singleton(KeyResolver::class, function ($app): KeyResolver {
            return new DefaultKeyResolver(
                normalizer: $app->make(ArgumentNormalizer::class),
                config: (array) $app['config']->get('cacheable', []),
            );
        });

        $this->app->singleton(CacheAspect::class, function ($app): CacheAspect {
            $queue = null;
            if ((bool) $app['config']->get('cacheable.swr.async', false)) {
                if ($app->bound(QueueFactory::class)) {
                    $queue = $app->make(QueueFactory::class)->connection(
                        $app['config']->get('cacheable.swr.queue_connection'),
                    );
                }
            }

            return new CacheableAspect(
                cache: $app->make(CacheFactory::class),
                keyResolver: $app->make(KeyResolver::class),
                events: $app->make(EventDispatcher::class),
                queue: $queue,
                config: (array) $app['config']->get('cacheable', []),
            );
        });

        $this->app->singleton(CacheableManager::class, function ($app): CacheableManager {
            return new CacheableManager(
                aspect: $app->make(CacheAspect::class),
                keyResolver: $app->make(KeyResolver::class),
                cache: $app->make(CacheFactory::class),
                container: $app->make(Container::class),
                config: (array) $app['config']->get('cacheable', []),
            );
        });

        // Friendly aliases
        $this->app->alias(CacheableManager::class, 'cacheable');
        $this->app->alias(CacheAspect::class, 'cacheable.aspect');

        $this->registerAutoProxy();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cacheable.php' => $this->configPath('cacheable.php'),
            ], 'cacheable-config');
        }
    }

    /**
     * Wrap configured classes with a CacheableProxy on resolution.
     */
    protected function registerAutoProxy(): void
    {
        $classes = (array) $this->app['config']->get('cacheable.auto_proxy', []);

        foreach ($classes as $abstract) {
            if (! is_string($abstract) || $abstract === '') {
                continue;
            }

            $this->app->extend($abstract, function ($instance, $app) {
                if ($instance instanceof CacheableProxy) {
                    return $instance;
                }

                return CacheableProxy::wrap($instance, $app->make(CacheAspect::class));
            });
        }
    }

    protected function configPath(string $file): string
    {
        return function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/'.$file);
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            CacheAspect::class,
            CacheableManager::class,
            KeyResolver::class,
            ArgumentNormalizer::class,
            'cacheable',
            'cacheable.aspect',
        ];
    }
}
