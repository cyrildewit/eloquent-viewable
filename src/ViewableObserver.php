<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Container\Container;

class ViewableObserver
{
    public function deleted(Viewable $viewable): void
    {
        if ($this->removeViewsOnDelete($viewable)) {
            Container::getInstance()->make(Views::class)->forViewable($viewable)->destroy();
        }
    }

    private function removeViewsOnDelete(Viewable $viewable): bool
    {
        return $viewable->removeViewsOnDelete ?? true;
    }
}
