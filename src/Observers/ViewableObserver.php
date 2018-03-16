<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Observers;

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
    public function deleted($model)
    {
        if (!$this->removeViewsOnDelete($model)) {
            return;
        }

        $likeable->removeViews();
    }

    /**
     * Determine if should remove views on model delete (defaults to true).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    private function removeViewsOnDelete($model): bool
    {
        return $model->removeViewsOnDelete ?? true;
    }
}
