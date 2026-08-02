<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Actions;

use CyrildeWit\EloquentViewable\Contracts\CreateView as CreateViewContract;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\PendingView;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class CreateView implements CreateViewContract
{
    public function __construct(
        private ViewContract $view,
        private Dispatcher $events,
    ) {}

    public function handle(PendingView $pending): ViewContract
    {
        /** @var ViewContract $view */
        $view = $this->view->create($pending->toArray());

        $this->events->dispatch(new ViewRecorded($view));

        return $view;
    }
}
