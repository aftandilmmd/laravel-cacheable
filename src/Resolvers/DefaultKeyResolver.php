<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Resolvers;

use Aftandilmmd\Cacheable\Attributes\Cacheable;
use Aftandilmmd\Cacheable\Contracts\ArgumentNormalizer;
use Aftandilmmd\Cacheable\Contracts\KeyResolver;
use Aftandilmmd\Cacheable\Support\CacheableConfig;
use ReflectionMethod;
use ReflectionParameter;

class DefaultKeyResolver implements KeyResolver
{
    public function __construct(
        protected readonly ArgumentNormalizer $normalizer,
        /** @var array<string, mixed> */
        protected readonly array $config,
    ) {}

    public function resolve(
        Cacheable $attribute,
        string $class,
        ReflectionMethod $method,
        array $args,
    ): string {
        $resolved = CacheableConfig::fromAttribute($attribute, $this->config);

        $named = $this->mapArgsToParameterNames($method, $args);
        $filtered = $this->filterArgs($named, $resolved);

        $body = $resolved->key !== null
            ? $this->replacePlaceholders($resolved->key, $named)
            : $this->autoKey($class, $method->getName(), $filtered, $resolved);

        $key = $this->joinSegments([$resolved->prefix, $resolved->version, $body]);

        return $this->enforceLength($key, $resolved);
    }

    /**
     * Map positional and named args to a [paramName => value] array,
     * filling in defaults for missing arguments.
     *
     * @param  array<int|string, mixed>  $args
     * @return array<string, mixed>
     */
    protected function mapArgsToParameterNames(ReflectionMethod $method, array $args): array
    {
        $named = [];

        foreach ($method->getParameters() as $i => $param) {
            /** @var ReflectionParameter $param */
            $name = $param->getName();

            if (array_key_exists($i, $args)) {
                $named[$name] = $args[$i];
            } elseif (array_key_exists($name, $args)) {
                $named[$name] = $args[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $named[$name] = $param->getDefaultValue();
            }
        }

        return $named;
    }

    /**
     * Apply keyParams / excludeParams filters.
     *
     * @param  array<string, mixed>  $named
     * @return array<string, mixed>
     */
    protected function filterArgs(array $named, CacheableConfig $cfg): array
    {
        if (! empty($cfg->keyParams)) {
            $named = array_intersect_key($named, array_flip($cfg->keyParams));
        }

        if (! empty($cfg->excludeParams)) {
            $named = array_diff_key($named, array_flip($cfg->excludeParams));
        }

        return $named;
    }

    /**
     * Replace `{paramName}` and nested `{paramName.property}` placeholders.
     *
     * @param  array<string, mixed>  $named
     */
    protected function replacePlaceholders(string $template, array $named): string
    {
        return preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/',
            function (array $m) use ($named): string {
                $path = explode('.', $m[1]);
                $value = $named[$path[0]] ?? null;

                for ($i = 1, $n = count($path); $i < $n; $i++) {
                    $segment = $path[$i];

                    if (is_object($value)) {
                        $value = isset($value->{$segment}) ? $value->{$segment} : null;
                    } elseif (is_array($value)) {
                        $value = $value[$segment] ?? null;
                    } else {
                        $value = null;
                        break;
                    }
                }

                return $this->scalarize($value);
            },
            $template,
        ) ?? $template;
    }

    /**
     * Generate an automatic key from class@method:hash(args).
     *
     * @param  array<string, mixed>  $args
     */
    protected function autoKey(string $class, string $method, array $args, CacheableConfig $cfg): string
    {
        $short = $this->shortClass($class);
        $payload = $this->serialize($this->normalizer->normalize($args), $cfg->serializer);
        $hash = hash($cfg->hashAlgo, $payload);

        return "{$short}@{$method}:{$hash}";
    }

    protected function shortClass(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    protected function serialize(mixed $value, string $serializer): string
    {
        return match ($serializer) {
            'serialize' => serialize($value),
            'igbinary' => function_exists('igbinary_serialize')
                ? igbinary_serialize($value)
                : serialize($value),
            default => json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        };
    }

    protected function scalarize(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            if (method_exists($value, 'getKey') && $value->getKey() !== null) {
                return (string) $value->getKey();
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
        }

        return hash('xxh128', json_encode($this->normalizer->normalize($value)) ?: 'null');
    }

    /**
     * @param  list<string|null>  $segments
     */
    protected function joinSegments(array $segments): string
    {
        return implode(':', array_filter(
            $segments,
            static fn ($v): bool => $v !== null && $v !== '',
        ));
    }

    /**
     * Truncate over-long keys deterministically using a hash suffix.
     */
    protected function enforceLength(string $key, CacheableConfig $cfg): string
    {
        if (strlen($key) <= $cfg->maxKeyLength) {
            return $key;
        }

        $hash = hash($cfg->hashAlgo, $key);
        $prefixLen = max(8, $cfg->maxKeyLength - strlen($hash) - 1);

        return substr($key, 0, $prefixLen).':'.$hash;
    }
}
