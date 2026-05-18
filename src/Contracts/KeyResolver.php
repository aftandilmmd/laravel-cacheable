<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Contracts;

use Aftandilmmd\Cacheable\Attributes\Cacheable;
use ReflectionMethod;

interface KeyResolver
{
    /**
     * Resolve the final cache key for a given method invocation.
     *
     * @param  array<int|string, mixed>  $args
     */
    public function resolve(
        Cacheable $attribute,
        string $class,
        ReflectionMethod $method,
        array $args,
    ): string;
}
