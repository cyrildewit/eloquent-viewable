<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;
use Illuminate\Container\Container;

class ViewableObserver
{
    /**
     * Handle the deleted event for the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $model
     * @return void
     */
    public function deleted(ViewableContract $viewable)
    {
        if ($this->removeViewsOnDelete($viewable)) {
            Container::getInstance()->make(Views::class)->forViewable($viewable)->destroy();
        }
    }

    /**
     * Determine if should remove views on model delete (defaults to true).
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @return bool
     */
    private function removeViewsOnDelete(ViewableContract $viewable): bool
    {
        return $viewable->removeViewsOnDelete ?? true;
    }
}
