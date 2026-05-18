<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Attributes;

use Attribute;

/**
 * Cacheable
 *
 * Annotate any method to have its return value cached automatically.
 *
 * @example
 * #[Cacheable(key: 'user.{id}', ttl: 3600, tags: ['users'])]
 * public function find(int $id): User { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Cacheable
{
    public function __construct(
        /**
         * Custom cache key with optional `{paramName}` and nested
         * `{object.property}` placeholders. When null, a key is generated
         * automatically from the class, method and arguments.
         */
        public ?string $key = null,

        /**
         * Prefix prepended to the resolved key. Falls back to config.
         */
        public ?string $prefix = null,

        /**
         * Time-to-live in seconds. Use null to cache forever.
         */
        public ?int $ttl = null,

        /**
         * Cache tags (only taggable stores: redis, memcached, array, …).
         *
         * @var list<string>
         */
        public array $tags = [],

        /**
         * Override the cache store. Falls back to config / default.
         */
        public ?string $store = null,

        /**
         * Whitelist of parameter names included in key generation.
         *
         * @var list<string>
         */
        public array $keyParams = [],

        /**
         * Blacklist of parameter names excluded from key generation.
         *
         * @var list<string>
         */
        public array $excludeParams = [],

        /**
         * Name of a method on the same instance returning bool. Caches only
         * when it returns true.
         */
        public ?string $when = null,

        /**
         * Name of a method on the same instance returning bool. Skips
         * caching when it returns true.
         */
        public ?string $unless = null,

        /**
         * Whether to store null return values. Falls back to config.
         */
        public ?bool $cacheNull = null,

        /**
         * Whether to store empty strings / arrays / Collections. Falls back
         * to config.
         */
        public ?bool $cacheEmpty = null,

        /**
         * Enable distributed lock-based stampede protection.
         */
        public bool $lock = false,

        /**
         * Maximum seconds to wait for the lock. Falls back to config.
         */
        public ?int $lockWait = null,

        /**
         * Random TTL jitter (seconds). Falls back to config.
         */
        public ?int $jitter = null,

        /**
         * 0..1 fraction of TTL within which to refresh-ahead. Falls back
         * to config.
         */
        public ?float $refreshAhead = null,

        /**
         * Keys / patterns to forget when this method is invoked.
         * Supports the same `{param}` placeholders as `key`.
         *
         * @var list<string>
         */
        public array $forget = [],

        /**
         * Tags to flush when this method is invoked (taggable stores only).
         *
         * @var list<string>
         */
        public array $forgetTags = [],

        /**
         * Version segment embedded into the key. Bump to invalidate.
         * Falls back to config.
         */
        public ?string $version = null,

        /**
         * Override the auto-key hash algorithm.
         */
        public ?string $hashAlgo = null,

        /**
         * Override the auto-key argument serializer: 'json' | 'serialize' |
         * 'igbinary'.
         */
        public ?string $serializer = null,
    ) {}
}
