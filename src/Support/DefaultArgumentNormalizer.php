<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Aftandilmmd\Cacheable\Contracts\ArgumentNormalizer;
use Stringable;
use UnitEnum;

/**
 * Recursively normalizes complex values into deterministic, hashable shapes.
 *
 * - Eloquent models -> [class => primaryKey]
 * - DateTime        -> ATOM string
 * - BackedEnum      -> [class => value]
 * - UnitEnum        -> [class => name]
 * - Stringable      -> (string) cast
 * - Arrayable       -> ->toArray()
 * - object          -> [class => public properties]
 * - scalar          -> itself
 */
class DefaultArgumentNormalizer implements ArgumentNormalizer
{
    public function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->normalize($v), $value);
        }

        if ($value instanceof BackedEnum) {
            return [$value::class => $value->value];
        }

        if ($value instanceof UnitEnum) {
            return [$value::class => $value->name];
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_object($value)) {
            // Eloquent models / anything with a primary key
            if (method_exists($value, 'getKey')) {
                $key = $value->getKey();
                if ($key !== null) {
                    return [$value::class => $key];
                }
            }

            if ($value instanceof Arrayable) {
                return [$value::class => $value->toArray()];
            }

            if ($value instanceof Stringable || method_exists($value, '__toString')) {
                return [$value::class => (string) $value];
            }

            return [$value::class => $this->normalize(get_object_vars($value))];
        }

        return null;
    }
}
