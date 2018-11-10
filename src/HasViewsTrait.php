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



trait HasViewsTrait
{
    /**
     * HasViews boot logic.
     *
     * @return void
     */
    public static function bootHasViewsTrait()
    {
        static::observe(HasViewsObserver::class);
    }

    /**
     * Get a collection of all the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views()
    {
        return views($this);
        return $this->morphMany(app(ViewContract::class), 'viewable');
    }

    // /**
    //  * Get the total views count.
    //  *
    //  * @param  \CyrildeWit\EloquentViewable\Period|null
    //  * @return int
    //  */
    // public function getViewsCount($period = null): int
    // {
    //     return views($this)->period($period)->count();
    // }

    // /**
    //  * Get the total views count.
    //  *
    //  * @param  \CyrildeWit\EloquentViewable\Period|null
    //  * @return int
    //  */
    // public function getUniqueViewsCount($period = null): int
    // {
    //     return views($this)->period($period)->unique()->count();
    // }

    // /**
    //  * Record a view.
    //  *
    //  * @return
    //  */
    // public function recordView($sessionDelay = null)
    // {
    //     return views($this)->sessionDelay($sessionDelay)->record();
    // }
}
