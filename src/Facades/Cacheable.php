<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Facades;

use Illuminate\Support\Facades\Facade;
use Aftandilmmd\Cacheable\CacheableManager;
use Aftandilmmd\Cacheable\Support\CacheableProxy;

/**
 * Facade for CacheableManager — the public API of the package.
 *
 * @method static CacheableProxy proxy(object $instance) Wrap an object in a transparent caching proxy. Method calls on the proxy are intercepted and cached when a #[Cacheable] attribute is present.
 * @method static mixed call(\Aftandilmmd\Cacheable\Attributes\Cacheable $attribute, string $owner, string $methodName, array $args, \Closure $callback) Execute a closure through the cache aspect without needing a class method.
 * @method static bool forget(string $class, string $method, array $args = []) Delete the cache entry for a specific class::method invocation.
 * @method static mixed refresh(string $class, string $method, array $args = []) Delete the cache entry and immediately re-execute the method to warm the cache with a fresh value.
 * @method static bool flushTags(array $tags, ?string $store = null) Flush all cache entries associated with the given tags (taggable stores only).
 * @method static string keyFor(string $class, string $method, array $args = []) Resolve the cache key that would be used for a given invocation — useful for debugging.
 *
 * @see CacheableManager
 */
class Cacheable extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CacheableManager::class;
    }
}
