<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Events;

/**
 * Fired after the method executes and its return value is stored in cache.
 */
final readonly class CacheWritten
{
    public function __construct(
        /** The resolved cache key under which the value was stored. */
        public string $key,
        /** Fully-qualified class name that owns the method. */
        public string $class,
        /** Method name that was intercepted. */
        public string $method,
        /** The value that was stored in cache. */
        public mixed $value,
        /** TTL in seconds. null means the value is cached forever. */
        public ?int $ttl,
    ) {}
}
