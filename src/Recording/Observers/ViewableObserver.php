<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Observers;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;

final readonly class ViewableObserver
{
    public function __construct(private ViewStore $store) {}

    public function deleted(Viewable $viewable): void
    {
        if ($this->isSoftDeleting($viewable)) {
            return;
        }

        if ($viewable->shouldRemoveViewsOnDelete()) {
            $this->store->forget($viewable);
        }
    }

    /**
     * Eloquent fires `deleted` for soft deletes as well. Those leave the row
     * in place, so the views stay with it; only a force delete removes them.
     */
    private function isSoftDeleting(Viewable $viewable): bool
    {
        return method_exists($viewable, 'isForceDeleting') && ! $viewable->isForceDeleting();
    }
}
