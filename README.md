# Laravel Cacheable

[![Tests](https://github.com/aftandilmmd/laravel-cacheable/actions/workflows/tests.yml/badge.svg)](https://github.com/aftandilmmd/laravel-cacheable/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/aftandilmmd/laravel-cacheable.svg)](https://packagist.org/packages/aftandilmmd/laravel-cacheable)
[![License](https://img.shields.io/packagist/l/aftandilmmd/laravel-cacheable.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/aftandilmmd/laravel-cacheable.svg)](composer.json)

> **Türkçe:** [README.tr.md](README.tr.md)

Add `#[Cacheable]` to any method. That's it — the package handles TTL, tags, locking, invalidation, and stale-while-revalidate automatically.

```php
#[Cacheable(key: 'user.{id}', ttl: 3600, tags: ['users'])]
public function find(int $id): User
{
    return User::findOrFail($id); // only runs on cache miss
}
```

**Requires:** PHP 8.2+ · Laravel 10 / 11 / 12 / 13

---

## Installation

```bash
composer require aftandilmmd/laravel-cacheable
```

Auto-discovered. No provider or alias registration needed.

---

## How it works

The package intercepts method calls and stores their return values in cache. On the next call with the same arguments, the cached value is returned without executing the method body.

There are two ways to make this interception happen.

---

## Real-world example

The most natural fit for this package is a **Repository** — a class that owns all database reads for a model. Annotate the read methods, attach `forget` to the write methods, register the repository in `auto_proxy`, and the rest is invisible.

```php
// app/Repositories/UserRepository.php

use Aftandilmmd\Cacheable\Attributes\Cacheable;

class UserRepository
{
    // Cache a single user for 1 hour.
    #[Cacheable(key: 'user.{id}', ttl: 3600, tags: ['users'])]
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    // Cache the active user list for 10 minutes.
    #[Cacheable(key: 'users.active', ttl: 600, tags: ['users'])]
    public function allActive(): Collection
    {
        return User::where('active', true)->get();
    }

    // On update: forget this user's entry and flush the list.
    #[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
    public function update(int $id, array $data): bool
    {
        return (bool) User::where('id', $id)->update($data);
    }

    // On delete: same invalidation.
    #[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
    public function delete(int $id): bool
    {
        return (bool) User::destroy($id);
    }
}
```

Register it once in config:

```php
// config/cacheable.php
'auto_proxy' => [
    App\Repositories\UserRepository::class,
],
```

Now every injected instance is automatically cached — no change to controllers or other callers:

```php
// app/Http/Controllers/UserController.php

class UserController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function show(int $id): JsonResponse
    {
        return response()->json($this->users->find($id)); // served from cache
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $this->users->update($id, $request->validated()); // updates DB + clears cache
        return response()->json($this->users->find($id)); // already fresh
    }
}
```

---

## Calling cached methods

### Option A — Auto-proxy (recommended)

Register your service in `config/cacheable.php`. Every container-resolved instance is then automatically wrapped — you call methods normally and caching is invisible.

```bash
php artisan vendor:publish --tag=cacheable-config
```

```php
// config/cacheable.php
'auto_proxy' => [
    App\Services\UserService::class,
],
```

```php
// app/Http/Controllers/UserController.php

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->find($id)); // cached
    }
}
```

> **Note:** `new UserService()` bypasses the container and won't be proxied. Always resolve through DI or `app()`.

### Option B — Manual proxy

No config needed. Wrap on the spot, call methods naturally:

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

$service = Cacheable::proxy(new UserService());
$user = $service->find(42); // cached
```

### Option C — Explicit dispatcher

If you prefer explicit over magic, use the `HasCacheableMethods` trait:

```php
// app/Services/UserService.php

use Aftandilmmd\Cacheable\Concerns\HasCacheableMethods;

class UserService
{
    use HasCacheableMethods;

    #[Cacheable(key: 'user.{id}', ttl: 3600)]
    public function find(int $id): User { ... }

    #[Cacheable(key: 'users.active', ttl: 600)]
    public static function active(): Collection { ... }
}
```

```php
$service->cached('find', [42]); // instance method
$service->cached('active');     // static method — same call
```

> **Self-call limitation:** PHP cannot intercept `$this->method()` inside the same class. Use `$this->cached('method', [...])` for internal calls.

### Static methods via proxy

`CacheableProxy::wrapClass` returns a proxy object that intercepts static calls by name:

```php
use Aftandilmmd\Cacheable\Support\CacheableProxy;

$proxy = CacheableProxy::wrapClass(UserService::class);
$proxy->active(); // calls UserService::active() through the cache layer
```

---

## Annotating methods

### Basic TTL

```php
#[Cacheable(ttl: 3600)]
public function all(): Collection { ... }
```

### Key with placeholders

```php
#[Cacheable(key: 'user.{id}.posts', ttl: 600)]
public function posts(int $id): Collection { ... }
```

Placeholders resolve to method argument values. Nested properties also work:

```php
#[Cacheable(key: 'order.{order.id}.items', ttl: 600)]
public function items(Order $order): Collection { ... }
```

### Cache forever

```php
#[Cacheable(ttl: null, tags: ['static'])]
public function countries(): array { ... }
```

### Tags

```php
#[Cacheable(ttl: 3600, tags: ['users'])]
public function find(int $id): User { ... }
```

Requires a taggable store (`redis`, `memcached`, `array`). The `file` and `database` drivers silently ignore tags.

### Conditional caching

Skip caching based on runtime conditions without changing the call site:

```php
#[Cacheable(ttl: 3600, when: 'shouldCache')]
public function expensive(string $type): array { ... }

public function shouldCache(string $type): bool
{
    return $type !== 'realtime';
}
```

`unless` is the inverse — skip cache when the method returns `true`.

### Exclude arguments from the key

Inject heavy objects (Request, Logger) without polluting the cache key:

```php
#[Cacheable(key: 'search.{q}', ttl: 300, excludeParams: ['request', 'logger'])]
public function search(string $q, Request $request, LoggerInterface $logger): array { ... }
```

---

## Cache invalidation

### On write methods

Attach `forget` or `forgetTags` to a mutating method. Cache entries are deleted automatically after the method runs:

```php
#[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
public function update(int $id, array $data): bool
{
    return User::find($id)->update($data);
}
```

### Via facade

Manually delete a specific entry or flush a tag group:

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

Cacheable::forget(UserService::class, 'find', [42]);
Cacheable::flushTags(['users']);
```

### Version bump

Bump the `version` parameter to invalidate everything at once without touching the cache store:

```php
// config/cacheable.php
'version' => env('CACHEABLE_VERSION', 'v2'), // was v1
```

Or per attribute:

```php
#[Cacheable(key: 'user.{id}', ttl: 3600, version: 'v2')]
```

---

## Stampede protection

When many concurrent requests miss the same key, only one should hit the database. Use a distributed lock:

```php
#[Cacheable(key: 'report.{date}', ttl: 3600, lock: true, lockWait: 15)]
public function heavyReport(string $date): array { ... }
```

To spread expiration times across many keys and avoid simultaneous cache misses, add jitter:

```php
#[Cacheable(ttl: 3600, jitter: 300)] // effective TTL: 3600–3900 seconds
public function popular(): array { ... }
```

---

## Stale-while-revalidate

Serve cached data immediately while refreshing in the background. `refreshAhead: 0.2` means "trigger a refresh in the final 20% of the TTL window":

```php
#[Cacheable(key: 'dashboard', ttl: 600, refreshAhead: 0.2)]
public function dashboardStats(): array { ... }
```

For async refresh via the queue, enable it in config:

```php
// config/cacheable.php
'swr' => [
    'async'            => true,
    'queue_connection' => 'redis',
    'queue_name'       => 'cache',
],
```

---

## Events

Listen in `app/Providers/AppServiceProvider.php`:

| Event | Fires when | Properties |
|-------|-----------|-----------|
| `CacheHit` | Cached value returned | `key`, `class`, `method`, `value` |
| `CacheMissed` | No cache — method will run | `key`, `class`, `method` |
| `CacheWritten` | Result stored in cache | `key`, `class`, `method`, `value`, `ttl` |
| `CacheForgotten` | Keys/tags invalidated | `class`, `method`, `keys`, `tags` |

```php
// app/Providers/AppServiceProvider.php

use Aftandilmmd\Cacheable\Events\CacheHit;
use Aftandilmmd\Cacheable\Events\CacheMissed;
use Aftandilmmd\Cacheable\Events\CacheWritten;
use Aftandilmmd\Cacheable\Events\CacheForgotten;

Event::listen(CacheHit::class, function (CacheHit $event) {
    Log::debug('Cache HIT', ['key' => $event->key, 'method' => $event->method]);
});

Event::listen(CacheMissed::class, function (CacheMissed $event) {
    Log::debug('Cache MISS', ['key' => $event->key, 'method' => $event->method]);
});

Event::listen(CacheWritten::class, function (CacheWritten $event) {
    Log::debug('Cache WRITTEN', ['key' => $event->key, 'ttl' => $event->ttl]);
});

Event::listen(CacheForgotten::class, function (CacheForgotten $event) {
    Log::debug('Cache FORGOTTEN', ['keys' => $event->keys, 'tags' => $event->tags]);
});
```

Disable all events globally: set `cacheable.events.enabled = false` in config.

---

## Debugging

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

// See exactly which key would be generated for a call
$key = Cacheable::keyFor(UserService::class, 'find', [42]);
// → "cacheable:v1:App\Services\UserService:find:a1b2c3..."

// Delete the cached value and immediately re-execute the method to warm cache
Cacheable::refresh(UserService::class, 'find', [42]);
```

If the key looks like a hash, set an explicit `key:` template in the attribute for human-readable keys.

---

## Configuration

Publish and edit `config/cacheable.php` to set global defaults. Every attribute parameter that accepts `null` falls back to these values.

```php
return [
    'enabled' => env('CACHEABLE_ENABLED', true),
    'store'   => env('CACHEABLE_STORE'),           // null = Laravel default
    'prefix'  => env('CACHEABLE_PREFIX', 'cacheable'),
    'version' => env('CACHEABLE_VERSION', 'v1'),
    'ttl'     => env('CACHEABLE_TTL', 3600),

    'keys' => [
        'hash_algo'  => 'xxh128',
        'serializer' => 'json',   // 'json' | 'serialize' | 'igbinary'
        'max_length' => 200,
    ],

    'stampede' => [
        'jitter'    => 0,
        'lock_wait' => 10,
    ],

    'swr' => [
        'refresh_ahead'    => 0.0,
        'async'            => false,
        'queue_connection' => env('CACHEABLE_SWR_CONNECTION'),
        'queue_name'       => env('CACHEABLE_SWR_QUEUE', 'default'),
    ],

    'storage' => [
        'cache_null'  => false,
        'cache_empty' => true,
    ],

    'events' => [
        'enabled' => true,
    ],

    'auto_proxy' => [
        // App\Services\UserService::class,
    ],
];
```

---

## Attribute reference

All parameters are optional. Omitting a parameter uses the config default.

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | `?string` | Key template. Supports `{param}` and `{param.property}` placeholders. Auto-generated when null. |
| `prefix` | `?string` | Prepended to the key. |
| `ttl` | `?int` | Seconds. `null` = cache forever. |
| `tags` | `string[]` | Tag groups. Requires taggable store. |
| `store` | `?string` | Override cache store. |
| `keyParams` | `string[]` | Whitelist: only these params are used in key generation. |
| `excludeParams` | `string[]` | Blacklist: these params are excluded from key generation. |
| `when` | `?string` | Method name on `$this` → cache only when it returns `true`. |
| `unless` | `?string` | Method name on `$this` → skip cache when it returns `true`. |
| `cacheNull` | `?bool` | Store `null` return values. |
| `cacheEmpty` | `?bool` | Store empty arrays / strings / Collections. |
| `lock` | `bool` | Enable distributed lock for stampede protection. |
| `lockWait` | `?int` | Seconds to wait for lock. |
| `jitter` | `?int` | Random seconds added to TTL. |
| `refreshAhead` | `?float` | `0..1` — fraction of TTL at which to trigger refresh. |
| `forget` | `string[]` | Key templates to delete after this method runs. |
| `forgetTags` | `string[]` | Tags to flush after this method runs. |
| `version` | `?string` | Embedded in key. Bump to invalidate all entries. |
| `hashAlgo` | `?string` | Hash algorithm for auto-generated keys. |
| `serializer` | `?string` | Argument serializer: `json` / `serialize` / `igbinary`. |

---

## Extending

### Custom key resolver

```php
// app/Services/TenantAwareKeyResolver.php

use Aftandilmmd\Cacheable\Contracts\KeyResolver;

class TenantAwareKeyResolver implements KeyResolver
{
    public function __construct(private KeyResolver $inner) {}

    public function resolve(...$args): string
    {
        return tenant()->id . ':' . $this->inner->resolve(...$args);
    }
}
```

```php
// app/Providers/AppServiceProvider.php

$this->app->extend(KeyResolver::class, fn ($inner) =>
    new TenantAwareKeyResolver($inner)
);
```

### Custom argument normalizer

```php
// app/Providers/AppServiceProvider.php

$this->app->singleton(ArgumentNormalizer::class, MyNormalizer::class);
```

You can also swap the entire caching pipeline by implementing `CacheAspect`.

---

## Troubleshooting

**`$this->method()` isn't cached.**
PHP can't intercept self-calls. Use `$this->cached('method', [...])` or inject a proxy.

**Static method isn't cached.**
`auto_proxy` and `Cacheable::proxy()` only wrap instances. Use `HasCacheableMethods` + `cached('method', [...])` or `CacheableProxy::wrapClass(MyClass::class)->method()`.

**Tags do nothing.**
Tags require a taggable store (`redis`, `memcached`, `array`). Switch the store or use `forget` keys instead.

**Cache isn't cleared between tests.**
Add `Cache::flush()` to your test's `setUp()`.

---

## License

MIT © Aftandilmmd. See [LICENSE.md](LICENSE.md).
