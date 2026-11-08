<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Cache;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Support\Defer\DeferredCallback;
use Illuminate\Support\Defer\DeferredCallbackCollection;

/**
 * A refresh also runs when the request failed, because the entry is stale
 * either way. Laravel keeps one callback per name, so a request refreshes an
 * entry once however often it reads it.
 *
 * The collection is looked up on every call because it is scoped to the
 * request, and an Octane worker would otherwise keep the first one. Outside a
 * Laravel application nothing runs deferred callbacks, so the refresh runs at
 * once.
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
