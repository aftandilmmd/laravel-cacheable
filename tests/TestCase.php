<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Tests;

use Aftandilmmd\Cacheable\CacheableServiceProvider;
use Aftandilmmd\Cacheable\Facades\Cacheable;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CacheableServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Cacheable' => Cacheable::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cacheable.prefix', 'test');
        $app['config']->set('cacheable.version', 'v1');
        $app['config']->set('cacheable.ttl', 3600);
    }
}
