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

trait Viewable
{
    /**
     * HasViews boot logic.
     *
     * @return void
     */
    public static function bootViewable()
    {
        static::observe(ViewableObserver::class);
    }

    /**
     * Get a collection of all the views the model has.
     *
     * @return \CyrildeWit\EloquentViewable\Views
     */
    public function views()
    {
        return views($this);
    }
}
