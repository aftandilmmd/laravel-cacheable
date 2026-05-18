<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Contracts;

use Closure;

interface CacheAspect
{
    /**
     * Apply the caching behaviour defined by any #[Cacheable] attribute on
     * the given method, falling back to executing $callback verbatim.
     *
     * @param  array<int|string, mixed>  $args
     * @param  Closure(): mixed  $callback
     */
    /**
     * @param  object|class-string  $instance
     */
    public function handle(
        object|string $instance,
        string $method,
        array $args,
        Closure $callback,
    ): mixed;
}
