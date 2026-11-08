<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\Defer\DeferredCallback;
use Illuminate\Support\Defer\DeferredCallbackCollection;

/**
 * Runs the refresh of a stale entry through Laravel's deferred callbacks:
 * after the response, or when the job or command ends. It runs also when
 * the request failed, because the entry is stale either way. One name is
 * refreshed once per request, and Laravel reports a refresh that throws, so
 * the stale entry stays until it expires.
 *
 * The collection is looked up on every call, because it is scoped to the
 * request. Without one, as outside a Laravel application, the refresh runs
 * at once.
 *
 * @internal
 */
final readonly class DeferredRefresh
{
    public function __construct(
        private ?ContainerContract $container = null,
    ) {}

    /** @param  Closure(): void  $refresh */
    public function schedule(string $name, Closure $refresh): void
    {
        $container = $this->container ?? Container::getInstance();

        if (! $container->bound(DeferredCallbackCollection::class)) {
            $refresh();

            return;
        }

        $container->make(DeferredCallbackCollection::class)[] = new DeferredCallback($refresh, $name, always: true);
    }
}
