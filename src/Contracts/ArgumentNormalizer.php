<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Contracts;

interface ArgumentNormalizer
{
    /**
     * Convert an arbitrary argument value into something safe to hash or
     * serialize (e.g. turn an Eloquent model into its primary key).
     */
    public function normalize(mixed $value): mixed;
}
