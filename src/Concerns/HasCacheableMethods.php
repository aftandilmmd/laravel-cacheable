<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Concerns;

use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use ReflectionMethod;

/**
 * Adds a `cached()` dispatcher to any class. Methods decorated with
 * #[Cacheable] are invoked through the aspect so caching is applied.
 *
 * Works for both instance and static methods:
 *   $service->cached('find', [$id]);
 *   $service->cached('active');
 */
trait HasCacheableMethods
{
    /**
     * @param  array<int|string, mixed>  $args
     */
    public function cached(string $method, array $args = []): mixed
    {
        /** @var CacheAspect $aspect */
        $aspect = app(CacheAspect::class);

        if ((new ReflectionMethod($this, $method))->isStatic()) {
            return $aspect->handle(
                instance: static::class,
                method: $method,
                args: $args,
                callback: static fn () => static::{$method}(...$args),
            );
        }

        return $aspect->handle(
            instance: $this,
            method: $method,
            args: $args,
            callback: fn () => $this->{$method}(...$args),
        );
    }
}
