# Laravel Cacheable

[![Tests](https://github.com/aftandilmmd/laravel-cacheable/actions/workflows/tests.yml/badge.svg)](https://github.com/aftandilmmd/laravel-cacheable/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/aftandilmmd/laravel-cacheable.svg)](https://packagist.org/packages/aftandilmmd/laravel-cacheable)
[![License](https://img.shields.io/packagist/l/aftandilmmd/laravel-cacheable.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/aftandilmmd/laravel-cacheable.svg)](composer.json)

> **English:** [README.md](README.md)

Herhangi bir method'a `#[Cacheable]` ekle. Hepsi bu — TTL, tag, kilit, invalidation ve stale-while-revalidate'i paket halleder.

```php
#[Cacheable(key: 'user.{id}', ttl: 3600, tags: ['users'])]
public function find(int $id): User
{
    return User::findOrFail($id); // sadece cache miss'te çalışır
}
```

**Gereksinimler:** PHP 8.2+ · Laravel 10 / 11 / 12 / 13

---

## Kurulum

```bash
composer require aftandilmmd/laravel-cacheable
```

Paket otomatik keşfedilir. Provider veya alias kaydına gerek yoktur.

---

## Nasıl çalışır

Paket method çağrılarını yakalar ve dönüş değerlerini cache'e yazar. Aynı argümanlarla yapılan sonraki çağrılarda method gövdesi çalıştırılmadan cache'deki değer döndürülür.

Bu yakalamayı sağlamanın iki yolu vardır.

---

## Gerçek dünya örneği

Bu paket için en doğal kullanım yeri **Repository**'dir — bir modelin tüm veritabanı okumalarını yöneten sınıf. Okuma method'larını annotate et, yazma method'larına `forget` ekle, repository'yi `auto_proxy`'ye kaydet; gerisini paket halleder.

```php
// app/Repositories/UserRepository.php

use Aftandilmmd\Cacheable\Attributes\Cacheable;

class UserRepository
{
    // Tek bir kullanıcıyı 1 saat cache'le.
    #[Cacheable(key: 'user.{id}', ttl: 3600, tags: ['users'])]
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    // Aktif kullanıcı listesini 10 dakika cache'le.
    #[Cacheable(key: 'users.active', ttl: 600, tags: ['users'])]
    public function allActive(): Collection
    {
        return User::where('active', true)->get();
    }

    // Güncelleme: bu kullanıcının cache'ini sil, listeyi flush et.
    #[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
    public function update(int $id, array $data): bool
    {
        return (bool) User::where('id', $id)->update($data);
    }

    // Silme: aynı invalidation.
    #[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
    public function delete(int $id): bool
    {
        return (bool) User::destroy($id);
    }
}
```

Config'de bir kez kaydet:

```php
// config/cacheable.php
'auto_proxy' => [
    App\Repositories\UserRepository::class,
],
```

Artık inject edilen her instance otomatik olarak cache'lenir — controller veya diğer çağıran sınıflarda hiçbir değişiklik gerekmez:

```php
// app/Http/Controllers/UserController.php

class UserController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function show(int $id): JsonResponse
    {
        return response()->json($this->users->find($id)); // cache'den gelir
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $this->users->update($id, $request->validated()); // DB günceller + cache'i temizler
        return response()->json($this->users->find($id)); // zaten taze
    }
}
```

---

## Cache'li method çağırma

### A Seçeneği — Auto-proxy (önerilen)

Servisini `config/cacheable.php`'ye kaydet. Container'dan resolve edilen her instance otomatik olarak proxy'lenir — method'ları normal çağırırsın, cacheleme görünmez olur.

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
        return response()->json($this->service->find($id)); // cachelendi
    }
}
```

> **Not:** `new UserService()` container'ı bypass eder ve proxy'lenmez. Her zaman DI veya `app()` üzerinden resolve et.

### B Seçeneği — Manuel proxy

Config gerekmez. Yerinde sar, method'ları doğal çağır:

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

$service = Cacheable::proxy(new UserService());
$user = $service->find(42); // cachelendi
```

### C Seçeneği — Explicit dispatcher

Sihirden çok açıklığı tercih ediyorsan `HasCacheableMethods` trait'ini kullan:

```php
// app/Services/UserService.php

use Aftandilmmd\Cacheable\Concerns\HasCacheableMethods;

class UserService
{
    use HasCacheableMethods;

    #[Cacheable(key: 'user.{id}', ttl: 3600)]
    public function find(int $id): User { ... }
}
```

```php
$service->cached('find', [42]); // explicit cache dispatch
```

> **Self-call kısıtlaması:** PHP, aynı sınıf içindeki `$this->method()` çağrılarını intercept edemez. İç çağrılarda `$this->cached('method', [...])` kullan.

---

## Method'lara annotation ekleme

### Basit TTL

```php
#[Cacheable(ttl: 3600)]
public function all(): Collection { ... }
```

### Placeholder'lı key

```php
#[Cacheable(key: 'user.{id}.posts', ttl: 600)]
public function posts(int $id): Collection { ... }
```

Placeholder'lar method argüman değerlerine çözümlenir. Nested property'ler de çalışır:

```php
#[Cacheable(key: 'order.{order.id}.items', ttl: 600)]
public function items(Order $order): Collection { ... }
```

### Sonsuza kadar cache'le

```php
#[Cacheable(ttl: null, tags: ['static'])]
public function countries(): array { ... }
```

### Tag'lar

```php
#[Cacheable(ttl: 3600, tags: ['users'])]
public function find(int $id): User { ... }
```

Taggable store gerektirir (`redis`, `memcached`, `array`). `file` ve `database` driver'ları tag'ları sessizce yok sayar.

### Koşullu cacheleme

Çağrı yerini değiştirmeden runtime koşullarına göre cache'i atla:

```php
#[Cacheable(ttl: 3600, when: 'shouldCache')]
public function expensive(string $type): array { ... }

public function shouldCache(string $type): bool
{
    return $type !== 'realtime';
}
```

`unless` tam tersidir — method `true` döndüğünde cache'i atlar.

### Argümanları key'den dışla

Ağır nesneleri (Request, Logger) inject ederken cache key'ini kirletme:

```php
#[Cacheable(key: 'search.{q}', ttl: 300, excludeParams: ['request', 'logger'])]
public function search(string $q, Request $request, LoggerInterface $logger): array { ... }
```

---

## Cache invalidation

### Write method'larında

Mutasyon yapan method'a `forget` veya `forgetTags` ekle. Cache girdileri method çalıştıktan sonra otomatik silinir:

```php
#[Cacheable(forget: ['user.{id}'], forgetTags: ['users'])]
public function update(int $id, array $data): bool
{
    return User::find($id)->update($data);
}
```

### Facade ile

Belirli bir girdiyi veya tag grubunu manuel sil:

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

Cacheable::forget(UserService::class, 'find', [42]);
Cacheable::flushTags(['users']);
```

### Version bump

Cache store'a dokunmadan her şeyi tek seferde geçersiz kılmak için `version` değerini artır:

```php
// config/cacheable.php
'version' => env('CACHEABLE_VERSION', 'v2'), // v1'di
```

Ya da attribute bazında:

```php
#[Cacheable(key: 'user.{id}', ttl: 3600, version: 'v2')]
```

---

## Stampede koruması

Aynı key'i çok sayıda eşzamanlı istek kaçırdığında yalnızca biri veritabanına gitmelidir. Dağıtık kilit kullan:

```php
#[Cacheable(key: 'report.{date}', ttl: 3600, lock: true, lockWait: 15)]
public function heavyReport(string $date): array { ... }
```

Birçok key'in aynı anda expire olmasını önlemek için jitter ekle:

```php
#[Cacheable(ttl: 3600, jitter: 300)] // efektif TTL: 3600–3900 saniye
public function popular(): array { ... }
```

---

## Stale-while-revalidate

Arka planda yenileme yapılırken cache'deki veriyi hemen sun. `refreshAhead: 0.2`, "TTL süresinin son %20'sinde yenileme başlat" anlamına gelir:

```php
#[Cacheable(key: 'dashboard', ttl: 600, refreshAhead: 0.2)]
public function dashboardStats(): array { ... }
```

Queue üzerinden async yenileme için config'de etkinleştir:

```php
// config/cacheable.php
'swr' => [
    'async'            => true,
    'queue_connection' => 'redis',
    'queue_name'       => 'cache',
],
```

---

## Eventler

`app/Providers/AppServiceProvider.php` içinde dinle:

| Event | Ne zaman | Properties |
|-------|----------|-----------|
| `CacheHit` | Cache'den değer döndü | `key`, `class`, `method`, `value` |
| `CacheMissed` | Cache yok — method çalışacak | `key`, `class`, `method` |
| `CacheWritten` | Sonuç cache'e yazıldı | `key`, `class`, `method`, `value`, `ttl` |
| `CacheForgotten` | Key/tag'lar silindi | `class`, `method`, `keys`, `tags` |

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

Tüm event'leri devre dışı bırakmak için: `cacheable.events.enabled = false`.

---

## Debugging

```php
use Aftandilmmd\Cacheable\Facades\Cacheable;

// Bir çağrı için hangi key'in üretileceğini gör
$key = Cacheable::keyFor(UserService::class, 'find', [42]);
// → "cacheable:v1:App\Services\UserService:find:a1b2c3..."

// Cache'i sil ve cache'i taze değerle doldurmak için metodu hemen yeniden çalıştır
Cacheable::refresh(UserService::class, 'find', [42]);
```

Key hash gibi görünüyorsa, attribute'da açık bir `key:` template belirt.

---

## Konfigürasyon

Global varsayılanları ayarlamak için `config/cacheable.php`'yi yayınla ve düzenle. `null` kabul eden her attribute parametresi bu değerlere geri döner.

```php
return [
    'enabled' => env('CACHEABLE_ENABLED', true),
    'store'   => env('CACHEABLE_STORE'),           // null = Laravel varsayılanı
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

## Attribute referansı

Tüm parametreler opsiyoneldir. Belirtilmeyenler config varsayılanını kullanır.

| Parametre | Tip | Açıklama |
|-----------|-----|---------|
| `key` | `?string` | Key template. `{param}` ve `{param.property}` placeholder'ları destekler. Null'da otomatik üretilir. |
| `prefix` | `?string` | Key'e eklenen ön ek. |
| `ttl` | `?int` | Saniye. `null` = sonsuza kadar cache'le. |
| `tags` | `string[]` | Tag grupları. Taggable store gerektirir. |
| `store` | `?string` | Cache store'u override et. |
| `keyParams` | `string[]` | Whitelist: yalnızca bu parametreler key üretiminde kullanılır. |
| `excludeParams` | `string[]` | Blacklist: bu parametreler key üretiminden çıkarılır. |
| `when` | `?string` | `$this` üzerindeki method adı → `true` döndüğünde cache'le. |
| `unless` | `?string` | `$this` üzerindeki method adı → `true` döndüğünde cache'i atla. |
| `cacheNull` | `?bool` | `null` dönüş değerlerini sakla. |
| `cacheEmpty` | `?bool` | Boş array / string / Collection'ları sakla. |
| `lock` | `bool` | Stampede koruması için dağıtık kilit etkinleştir. |
| `lockWait` | `?int` | Kilit için beklenecek saniye. |
| `jitter` | `?int` | TTL'e eklenen rastgele saniye. |
| `refreshAhead` | `?float` | `0..1` — yenilemenin tetikleneceği TTL oranı. |
| `forget` | `string[]` | Bu method çalıştıktan sonra silinecek key template'leri. |
| `forgetTags` | `string[]` | Bu method çalıştıktan sonra flush edilecek tag'lar. |
| `version` | `?string` | Key'e gömülür. Tüm girdileri geçersiz kılmak için artır. |
| `hashAlgo` | `?string` | Otomatik üretilen key'ler için hash algoritması. |
| `serializer` | `?string` | Argüman serializerı: `json` / `serialize` / `igbinary`. |

---

## Genişletme

### Özel key resolver

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

### Özel argüman normalizer

```php
// app/Providers/AppServiceProvider.php

$this->app->singleton(ArgumentNormalizer::class, MyNormalizer::class);
```

`CacheAspect` contract'ını implement ederek tüm cache pipeline'ını da değiştirebilirsin.

---

## Sorun giderme

**`$this->method()` cache'lenmiyor.**
PHP self-call'ları intercept edemez. `$this->cached('method', [...])` kullan veya proxy inject et.

**Tag'lar çalışmıyor.**
Tag'lar taggable store gerektirir (`redis`, `memcached`, `array`). Store'u değiştir veya `forget` key'lerini kullan.

**Test'ler arası cache sıfırlanmıyor.**
Test `setUp()` metoduna `Cache::flush()` ekle.

---

## Lisans

MIT © Aftandilmmd. Detay için [LICENSE.md](LICENSE.md).
