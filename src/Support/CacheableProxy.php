<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Support;

use BadMethodCallException;
use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use ReflectionMethod;

/**
 * Transparent proxy that intercepts method calls on the wrapped target and
 * applies caching when a #[Cacheable] attribute is present.
 *
 * @template T of object
 *
 * @mixin T
 */
final class CacheableProxy
{
    /**
     * @param  T  $target
     */
    public function __construct(
        private readonly object $target,
        private readonly CacheAspect $aspect,
    ) {}

    /**
     * @template TObj of object
     *
     * @param  TObj  $instance
     * @return self<TObj>
     */
    public static function wrap(object $instance, ?CacheAspect $aspect = null): self
    {
        return new self($instance, $aspect ?? app(CacheAspect::class));
    }

    /**
     * Wraps a class string for static method caching.
     *
     * @param  class-string  $class
     */
    public static function wrapClass(string $class, ?CacheAspect $aspect = null): StaticCacheableProxy
    {
        return new StaticCacheableProxy($class, $aspect ?? app(CacheAspect::class));
    }

    /**
     * @param  array<int|string, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        if (! method_exists($this->target, $method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                $this->target::class,
                $method,
            ));
        }

        $reflection = new ReflectionMethod($this->target, $method);

        if ($reflection->getAttributes(Cacheable::class) === []) {
            return $this->target->{$method}(...$args);
        }

        return $this->aspect->handle(
            instance: $this->target,
            method: $method,
            args: $args,
            callback: fn () => $this->target->{$method}(...$args),
        );
    }

    public function __get(string $name): mixed
    {
        return $this->target->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->target->{$name} = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->target->{$name});
    }

    /**
     * @return T
     */
    public function getTarget(): object
    {
        return $this->target;
    }
}
