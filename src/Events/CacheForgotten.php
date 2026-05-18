<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Events;

/**
 * Fired when cache keys or tags are invalidated via forget / forgetTags directives.
 */
final readonly class CacheForgotten
{
    /**
     * @param  list<string>  $keys  Cache keys that were deleted.
     * @param  list<string>  $tags  Tags that were flushed.
     */
    public function __construct(
        /** Fully-qualified class name that owns the method. */
        public string $class,
        /** Method name whose execution triggered the invalidation. */
        public string $method,
        public array $keys = [],
        public array $tags = [],
    ) {}
}
