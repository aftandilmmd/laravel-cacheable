<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Tests\Unit;

use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Resolvers\DefaultKeyResolver;
use Aftandilmmd\Cacheable\Support\DefaultArgumentNormalizer;
use Aftandilmmd\Cacheable\Tests\Fixtures\UserService;
use Aftandilmmd\Cacheable\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

class KeyResolverTest extends TestCase
{
    private function resolver(): DefaultKeyResolver
    {
        return new DefaultKeyResolver(
            new DefaultArgumentNormalizer,
            (array) config('cacheable'),
        );
    }

    #[Test]
    public function placeholder_replacement_works(): void
    {
        $attr = new Cacheable(key: 'user.{id}');
        $method = new ReflectionMethod(UserService::class, 'profile');

        $key = $this->resolver()->resolve($attr, UserService::class, $method, [99]);

        $this->assertSame('test:v1:user.99', $key);
    }

    #[Test]
    public function nested_placeholder_works(): void
    {
        $attr = new Cacheable(key: 'order.{order.id}');

        $svc = new class
        {
            public function items(object $order): array
            {
                return [];
            }
        };

        $method = new ReflectionMethod($svc, 'items');
        $order = (object) ['id' => 77];

        $key = $this->resolver()->resolve($attr, $svc::class, $method, [$order]);

        $this->assertStringContainsString('order.77', $key);
    }

    #[Test]
    public function auto_key_is_deterministic(): void
    {
        $attr = new Cacheable;
        $method = new ReflectionMethod(UserService::class, 'autoKey');

        $k1 = $this->resolver()->resolve($attr, UserService::class, $method, [1]);
        $k2 = $this->resolver()->resolve($attr, UserService::class, $method, [1]);

        $this->assertSame($k1, $k2);
    }

    #[Test]
    public function long_keys_are_hash_truncated(): void
    {
        config()->set('cacheable.keys.max_length', 50);
        $attr = new Cacheable(key: str_repeat('a', 500));
        $method = new ReflectionMethod(UserService::class, 'autoKey');

        $key = $this->resolver()->resolve($attr, UserService::class, $method, [1]);

        $this->assertLessThanOrEqual(50, strlen($key));
    }

    #[Test]
    public function backed_enum_argument_is_normalized(): void
    {
        $normalizer = new DefaultArgumentNormalizer;
        $value = TestStatusEnum::Active;

        $normalized = $normalizer->normalize($value);

        $this->assertIsArray($normalized);
        $this->assertSame(['active'], array_values($normalized));
    }
}

enum TestStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
