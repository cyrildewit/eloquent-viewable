<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Container\Container;

class ViewableObserver
{
    public function deleted(Viewable $viewable): void
    {
        if ($this->isSoftDeleting($viewable)) {
            return;
        }

        if ($this->removeViewsOnDelete($viewable)) {
            Container::getInstance()->make(Views::class)->forViewable($viewable)->destroy();
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

    private function removeViewsOnDelete(Viewable $viewable): bool
    {
        return $viewable->removeViewsOnDelete ?? true;
    }
}
