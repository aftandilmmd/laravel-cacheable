<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Set this to false to bypass caching entirely. Method bodies will run
    | every time as if no #[Cacheable] attribute were present. Useful for
    | local development or debugging.
    |
    */
    'enabled' => env('CACHEABLE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Store
    |--------------------------------------------------------------------------
    |
    | The cache store used when an attribute does not declare one. Setting
    | this to null falls back to Laravel's default cache store.
    |
    */
    'store' => env('CACHEABLE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Global Prefix
    |--------------------------------------------------------------------------
    |
    | Prepended to every cache key produced by this package. Override on a
    | per-method basis via the `prefix` attribute argument.
    |
    */
    'prefix' => env('CACHEABLE_PREFIX', 'cacheable'),

    /*
    |--------------------------------------------------------------------------
    | Default Version
    |--------------------------------------------------------------------------
    |
    | Embedded into every key. Bump this (e.g. on deploy) to invalidate all
    | cached values globally without flushing the underlying store.
    |
    */
    'version' => env('CACHEABLE_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | TTL applied when an attribute does not declare one. Use null to
    | default to "forever".
    |
    */
    'ttl' => env('CACHEABLE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Key Generation
    |--------------------------------------------------------------------------
    */
    'keys' => [
        // Hash algorithm for auto-generated keys (anything supported by hash()).
        'hash_algo' => env('CACHEABLE_HASH_ALGO', 'xxh128'),

        // Argument serialization for hashing: 'json' | 'serialize' | 'igbinary'
        'serializer' => env('CACHEABLE_SERIALIZER', 'json'),

        // Maximum key length. Keys longer than this are hashed.
        'max_length' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stampede Protection
    |--------------------------------------------------------------------------
    |
    | When many concurrent requests miss the cache simultaneously they can
    | overwhelm the origin. These defaults are applied unless overridden
    | per-attribute.
    |
    */
    'stampede' => [
        // Apply random jitter (0..N seconds) to TTL to spread expiration.
        'jitter' => env('CACHEABLE_JITTER', 0),

        // Default lock wait time (seconds) when lock: true is used.
        'lock_wait' => 10,

        // Lock prefix.
        'lock_prefix' => 'cacheable:lock:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale-While-Revalidate
    |--------------------------------------------------------------------------
    |
    | Refresh a value asynchronously when it enters its "stale" window.
    | `refresh_ahead` is a 0..1 fraction of the TTL.
    |
    */
    'swr' => [
        // 0 disables SWR by default. e.g. 0.2 = refresh in the final 20% of TTL.
        'refresh_ahead' => env('CACHEABLE_REFRESH_AHEAD', 0.0),

        // Use the queue for refresh jobs. When false, refresh runs inline.
        'async' => env('CACHEABLE_SWR_ASYNC', false),

        // Queue connection / name to dispatch refresh jobs onto.
        'queue_connection' => env('CACHEABLE_SWR_CONNECTION'),
        'queue_name' => env('CACHEABLE_SWR_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stored Value Filtering
    |--------------------------------------------------------------------------
    */
    'storage' => [
        // Cache null return values?
        'cache_null' => false,

        // Cache empty strings / arrays / Collections?
        'cache_empty' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Toggle dispatching of CacheHit, CacheMissed, CacheWritten,
    | CacheForgotten events. Disable in hot paths if you don't need them.
    |
    */
    'events' => [
        'enabled' => env('CACHEABLE_EVENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Proxy
    |--------------------------------------------------------------------------
    |
    | Classes listed here are automatically wrapped with CacheableProxy on
    | resolution from the container. Use this so callers can invoke methods
    | naturally (`$service->find($id)`) and still hit the cache layer.
    |
    */
    'auto_proxy' => [
        // 'App\Services\UserService',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('CACHEABLE_LOG', false),
        'channel' => env('CACHEABLE_LOG_CHANNEL'),
        'level' => env('CACHEABLE_LOG_LEVEL', 'debug'),
    ],

];
