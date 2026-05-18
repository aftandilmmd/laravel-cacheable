<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Tests\Fixtures;

use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Concerns\HasCacheableMethods;

class UserService
{
    use HasCacheableMethods;

    public int $calls = 0;

    #[Cacheable(ttl: 3600)]
    public function autoKey(int $id): array
    {
        $this->calls++;

        return ['id' => $id];
    }

    #[Cacheable(key: 'user.{id}.profile', ttl: 1800)]
    public function profile(int $id): array
    {
        $this->calls++;

        return ['user_id' => $id];
    }

    #[Cacheable(key: 'search:{q}', ttl: 600, keyParams: ['q'])]
    public function search(string $q, mixed $logger = null): array
    {
        $this->calls++;

        return ['q' => $q];
    }

    #[Cacheable(ttl: 3600, excludeParams: ['logger'])]
    public function excluded(int $id, mixed $logger = null): array
    {
        $this->calls++;

        return ['id' => $id];
    }

    #[Cacheable(key: 'cond.{type}', ttl: 3600, when: 'shouldCache')]
    public function withWhen(string $type): array
    {
        $this->calls++;

        return ['type' => $type];
    }

    public function shouldCache(string $type): bool
    {
        return $type !== 'realtime';
    }

    #[Cacheable(key: 'cond.{type}', ttl: 3600, unless: 'isDebug')]
    public function withUnless(string $type): array
    {
        $this->calls++;

        return ['type' => $type];
    }

    public function isDebug(string $type): bool
    {
        return $type === 'debug';
    }

    #[Cacheable(key: 'maybe.{id}', ttl: 3600, cacheNull: false)]
    public function maybeNull(int $id): ?array
    {
        $this->calls++;

        return $id > 0 ? ['id' => $id] : null;
    }

    #[Cacheable(key: 'empty.{id}', ttl: 3600, cacheEmpty: false)]
    public function maybeEmpty(int $id): array
    {
        $this->calls++;

        return $id > 0 ? ['id' => $id] : [];
    }

    #[Cacheable(key: 'versioned', ttl: 3600, version: 'v9')]
    public function versioned(): string
    {
        $this->calls++;

        return 'data';
    }

    #[Cacheable(key: 'lock.{id}', ttl: 3600, lock: true, lockWait: 2)]
    public function locked(int $id): array
    {
        $this->calls++;

        return ['id' => $id];
    }

    #[Cacheable(key: 'forever')]
    public function forever(): string
    {
        $this->calls++;

        return 'forever';
    }

    #[Cacheable(prefix: 'inv', forget: ['user.{id}.profile'])]
    public function invalidate(int $id): bool
    {
        return true;
    }

    public function unCached(): string
    {
        $this->calls++;

        return 'plain';
    }
}
