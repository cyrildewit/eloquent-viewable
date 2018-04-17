<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable;

/**
 * Class ViewableObserver.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableObserver
{
    /**
     * Handle the deleted event for the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function deleted($viewable)
    {
        if (! $this->removeViewsOnDelete($viewable)) {
            return;
        }

        $viewable->removeViews();
    }

    /**
     * Determine if should remove views on model delete (defaults to true).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return bool
     */
    private function removeViewsOnDelete($viewable): bool
    {
        return $viewable->removeViewsOnDelete ?? true;
    }
}
