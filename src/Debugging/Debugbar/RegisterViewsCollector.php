<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Debugging\Debugbar;

use CyrildeWit\EloquentViewable\Recording\Events\ViewAttempted;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Adds the collector once the application has booted, which is after Debugbar
 * has booted itself. Debugbar only resolves its instance when it can be
 * enabled, so an application without Debugbar, or with it turned off, never
 * gets one from here.
 *
 * Set `debugbar.collectors.eloquent_viewable` to false to leave it out.
 */
final readonly class RegisterViewsCollector
{
    public function __construct(
        private Container $container,
        private Dispatcher $events,
    ) {}

    public function __invoke(): void
    {
        if (! $this->container->resolved(LaravelDebugbar::class)) {
            return;
        }

        $debugbar = $this->container->make(LaravelDebugbar::class);

        if (! $debugbar->isCollecting()
            || ! $debugbar->shouldCollect(ViewsCollector::Name)
            || $debugbar->hasCollector(ViewsCollector::Name)) {
            return;
        }

        $collector = new ViewsCollector;

        $debugbar->addCollector($collector);

        $this->events->listen(ViewAttempted::class, $collector->addAttempt(...));
    }
}
