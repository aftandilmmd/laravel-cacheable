<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Concerns;

use Aftandilmmd\Cacheable\Contracts\CacheAspect;

/**
 * Adds a `cached()` dispatcher to any class. Methods decorated with
 * #[Cacheable] are invoked through the aspect so caching is applied.
 *
 * @example
 *   $service->cached('find', [$id]);
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

        return $aspect->handle(
            instance: $this,
            method: $method,
            args: $args,
            callback: fn () => $this->{$method}(...$args),
        );
    }
}
