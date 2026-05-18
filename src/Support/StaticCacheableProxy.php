<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Support;

use BadMethodCallException;
use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;
use ReflectionMethod;

/**
 * Proxy that intercepts static method calls on a class and applies caching
 * when a #[Cacheable] attribute is present.
 *
 * @template T
 */
final class StaticCacheableProxy
{
    /**
     * @param  class-string<T>  $class
     */
    public function __construct(
        private readonly string $class,
        private readonly CacheAspect $aspect,
    ) {}

    /**
     * @param  array<int|string, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        if (! method_exists($this->class, $method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                $this->class,
                $method,
            ));
        }

        $reflection = new ReflectionMethod($this->class, $method);

        if ($reflection->getAttributes(Cacheable::class) === []) {
            return ($this->class)::{$method}(...$args);
        }

        return $this->aspect->handle(
            instance: $this->class,
            method: $method,
            args: $args,
            callback: fn () => ($this->class)::{$method}(...$args),
        );
    }

    /**
     * @return class-string<T>
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
