<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Aftandilmmd\Cacheable\Events\CacheHit;
use Aftandilmmd\Cacheable\Events\CacheMissed;
use Aftandilmmd\Cacheable\Events\CacheWritten;
use Aftandilmmd\Cacheable\Facades\Cacheable;
use Aftandilmmd\Cacheable\Support\CacheableProxy;
use Aftandilmmd\Cacheable\Tests\Fixtures\UserService;
use Aftandilmmd\Cacheable\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CacheableAttributeTest extends TestCase
{
    #[Test]
    public function it_caches_method_result_after_first_call(): void
    {
        $svc = new UserService;

        $svc->cached('autoKey', [1]);
        $svc->cached('autoKey', [1]);
        $svc->cached('autoKey', [1]);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function different_arguments_produce_different_cache_entries(): void
    {
        $svc = new UserService;

        $svc->cached('autoKey', [1]);
        $svc->cached('autoKey', [2]);

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function custom_key_with_placeholder_is_stored(): void
    {
        $svc = new UserService;
        $svc->cached('profile', [42]);

        $this->assertTrue(Cache::has('test:v1:user.42.profile'));
    }

    #[Test]
    public function key_params_whitelist_filters_arguments(): void
    {
        $svc = new UserService;
        $svc->cached('search', ['foo', 'logger-A']);
        $svc->cached('search', ['foo', 'logger-B']);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function exclude_params_drops_them_from_key(): void
    {
        $svc = new UserService;
        $svc->cached('excluded', [1, 'log1']);
        $svc->cached('excluded', [1, 'log2']);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function when_predicate_can_skip_caching(): void
    {
        $svc = new UserService;
        $svc->cached('withWhen', ['realtime']);
        $svc->cached('withWhen', ['realtime']);

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function when_predicate_allows_caching(): void
    {
        $svc = new UserService;
        $svc->cached('withWhen', ['normal']);
        $svc->cached('withWhen', ['normal']);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function unless_predicate_skips_caching_when_true(): void
    {
        $svc = new UserService;
        $svc->cached('withUnless', ['debug']);
        $svc->cached('withUnless', ['debug']);

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function null_returns_are_not_cached_when_cache_null_is_false(): void
    {
        $svc = new UserService;
        $svc->cached('maybeNull', [0]);
        $svc->cached('maybeNull', [0]);

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function empty_arrays_are_not_cached_when_cache_empty_is_false(): void
    {
        $svc = new UserService;
        $svc->cached('maybeEmpty', [0]);
        $svc->cached('maybeEmpty', [0]);

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function version_segment_appears_in_key(): void
    {
        $svc = new UserService;
        $svc->cached('versioned', []);

        $this->assertTrue(Cache::has('test:v9:versioned'));
    }

    #[Test]
    public function lock_does_not_break_on_array_driver(): void
    {
        $svc = new UserService;
        $svc->cached('locked', [1]);
        $svc->cached('locked', [1]);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function null_ttl_caches_forever(): void
    {
        $svc = new UserService;
        $svc->cached('forever', []);
        $svc->cached('forever', []);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function forget_directive_removes_cached_entry(): void
    {
        $svc = new UserService;
        $svc->cached('profile', [7]);
        $this->assertTrue(Cache::has('test:v1:user.7.profile'));

        // invalidate() declares prefix: 'inv' but forget should still use
        // the same prefix as the attribute itself (its prefix=inv), not
        // profile()'s. So we need to align: invalidate uses prefix=inv
        // here intentionally - assert that THIS forget works at its own prefix.
        Cache::put('inv:v1:user.7.profile', 'x', 3600);
        $svc->cached('invalidate', [7]);
        $this->assertFalse(Cache::has('inv:v1:user.7.profile'));
    }

    #[Test]
    public function un_cacheable_method_runs_every_time(): void
    {
        $svc = new UserService;
        $svc->cached('unCached', []);
        $svc->cached('unCached', []);
        // unCached has no attribute - cached() just invokes it via aspect,
        // which sees no attribute and runs the callback each time.
        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function proxy_intercepts_method_calls_transparently(): void
    {
        $svc = new UserService;
        /** @var UserService $proxy */
        $proxy = CacheableProxy::wrap($svc);

        $proxy->autoKey(1);
        $proxy->autoKey(1);
        $proxy->autoKey(1);

        $this->assertSame(1, $svc->calls);
    }

    #[Test]
    public function proxy_passes_through_un_cacheable_methods(): void
    {
        $svc = new UserService;
        /** @var UserService $proxy */
        $proxy = CacheableProxy::wrap($svc);

        $proxy->unCached();
        $proxy->unCached();

        $this->assertSame(2, $svc->calls);
    }

    #[Test]
    public function facade_resolves_keys(): void
    {
        $key = Cacheable::keyFor(UserService::class, 'profile', [42]);
        $this->assertSame('test:v1:user.42.profile', $key);
    }

    #[Test]
    public function facade_can_forget_a_specific_invocation(): void
    {
        $svc = new UserService;
        $svc->cached('profile', [9]);

        $this->assertTrue(Cache::has('test:v1:user.9.profile'));

        Cacheable::forget(UserService::class, 'profile', [9]);
        $this->assertFalse(Cache::has('test:v1:user.9.profile'));
    }

    #[Test]
    public function events_are_dispatched(): void
    {
        Event::fake([CacheHit::class, CacheMissed::class, CacheWritten::class]);

        $svc = new UserService;
        $svc->cached('autoKey', [123]);
        $svc->cached('autoKey', [123]);

        Event::assertDispatched(CacheMissed::class);
        Event::assertDispatched(CacheWritten::class);
        Event::assertDispatched(CacheHit::class);
    }

    #[Test]
    public function master_switch_disables_caching(): void
    {
        config()->set('cacheable.enabled', false);

        $svc = new UserService;
        $svc->cached('autoKey', [1]);
        $svc->cached('autoKey', [1]);
        $svc->cached('autoKey', [1]);

        $this->assertSame(3, $svc->calls);
    }
}
