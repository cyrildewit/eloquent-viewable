<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Actions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Events\ViewsDestroyed;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class DestroyViews
{
    public function __construct(
        private ViewStore $store,
        private Dispatcher $events,
    ) {}

    public function handle(Viewable $viewable): void
    {
        $this->store->forget($viewable);

        $this->events->dispatch(new ViewsDestroyed($viewable));
    }
}
