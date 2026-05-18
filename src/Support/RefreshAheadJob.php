<?php

declare(strict_types=1);

namespace Aftandilmmd\Cacheable\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Aftandilmmd\Cacheable\Contracts\CacheAspect;

/**
 * Background job that re-invokes a Cacheable method to refresh its cache
 * entry before it expires. Dispatched by the SWR pathway in
 * CacheableAspect when stale-while-revalidate is enabled in async mode.
 */
class RefreshAheadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $class,
        public string $method,
        public string $key,
    ) {}

    public function handle(CacheAspect $aspect): void
    {
        if (! class_exists($this->class)) {
            return;
        }

        $instance = app($this->class);

        // We can't reconstruct the original args here, so the refresh
        // strategy is to ask the user to register a "warmer" closure via
        // their service, or to use sync refresh. The job is dispatched
        // only when the user has explicitly enabled async mode in config,
        // and is expected to be paired with a parameterless warm-up
        // method on the cached service for the SWR pattern.
        if (method_exists($instance, 'warm'.ucfirst($this->method))) {
            $instance->{'warm'.ucfirst($this->method)}();
        }
    }
}
