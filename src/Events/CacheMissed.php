<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Events;

/**
 * Fired when no cached value exists and the method is about to be executed.
 */
final readonly class CacheMissed
{
    public function __construct(
        /** The resolved cache key that was looked up. */
        public string $key,
        /** Fully-qualified class name that owns the method. */
        public string $class,
        /** Method name that was intercepted. */
        public string $method,
    ) {}
}
