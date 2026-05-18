<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Support;

use Aftandilmmd\Cacheable\Attributes\Cacheable;

/**
 * Resolved, effective settings for a single Cacheable invocation.
 *
 * Merges the attribute's per-method overrides with the package config so
 * downstream components don't have to know about either source.
 */
final readonly class CacheableConfig
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $keyParams
     * @param  list<string>  $excludeParams
     * @param  list<string>  $forget
     * @param  list<string>  $forgetTags
     */
    public function __construct(
        public ?string $key,
        public string $prefix,
        public string $version,
        public ?int $ttl,
        public array $tags,
        public ?string $store,
        public array $keyParams,
        public array $excludeParams,
        public ?string $when,
        public ?string $unless,
        public bool $cacheNull,
        public bool $cacheEmpty,
        public bool $lock,
        public int $lockWait,
        public int $jitter,
        public float $refreshAhead,
        public array $forget,
        public array $forgetTags,
        public string $hashAlgo,
        public string $serializer,
        public int $maxKeyLength,
        public bool $swrAsync,
        public ?string $swrQueueConnection,
        public string $swrQueueName,
    ) {}

    /**
     * Build a resolved config from an attribute + package config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromAttribute(Cacheable $attr, array $config): self
    {
        return new self(
            key: $attr->key,
            prefix: $attr->prefix ?? (string) ($config['prefix'] ?? 'cacheable'),
            version: $attr->version ?? (string) ($config['version'] ?? 'v1'),
            ttl: $attr->ttl ?? ($config['ttl'] ?? null),
            tags: $attr->tags,
            store: $attr->store ?? ($config['store'] ?? null),
            keyParams: $attr->keyParams,
            excludeParams: $attr->excludeParams,
            when: $attr->when,
            unless: $attr->unless,
            cacheNull: $attr->cacheNull ?? (bool) ($config['storage']['cache_null'] ?? false),
            cacheEmpty: $attr->cacheEmpty ?? (bool) ($config['storage']['cache_empty'] ?? true),
            lock: $attr->lock,
            lockWait: $attr->lockWait ?? (int) ($config['stampede']['lock_wait'] ?? 10),
            jitter: $attr->jitter ?? (int) ($config['stampede']['jitter'] ?? 0),
            refreshAhead: $attr->refreshAhead ?? (float) ($config['swr']['refresh_ahead'] ?? 0.0),
            forget: $attr->forget,
            forgetTags: $attr->forgetTags,
            hashAlgo: $attr->hashAlgo ?? (string) ($config['keys']['hash_algo'] ?? 'xxh128'),
            serializer: $attr->serializer ?? (string) ($config['keys']['serializer'] ?? 'json'),
            maxKeyLength: (int) ($config['keys']['max_length'] ?? 200),
            swrAsync: (bool) ($config['swr']['async'] ?? false),
            swrQueueConnection: $config['swr']['queue_connection'] ?? null,
            swrQueueName: (string) ($config['swr']['queue_name'] ?? 'default'),
        );
    }
}
