<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Events;

/**
 * Fired when a cached value is returned without executing the method.
 */
final readonly class CacheHit
{
    public function __construct(
        /** The resolved cache key. */
        public string $key,
        /** Fully-qualified class name that owns the method. */
        public string $class,
        /** Method name that was intercepted. */
        public string $method,
        /** The value returned from cache. */
        public mixed $value,
    ) {}
}
