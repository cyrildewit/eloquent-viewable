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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;

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
     * Get the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views(): MorphMany
    {
        return $this->morphMany(app(ViewContract::class), 'viewable');
    }

    /**
     * Scope a query to order records by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByViews(Builder $query, string $direction = 'desc', $period = null): Builder
    {
        return $query->withCount(['views' => function ($query) use ($period) {
            if ($period) {
                $query->withinPeriod($period);
            }
        }])->orderBy('views_count', $direction);
    }

    /**
     * Scope a query to order records by unique views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByUniqueViews(Builder $query, string $direction = 'desc', $period = null): Builder
    {
        return $query->withCount(['views' => function ($query) use ($period) {
            $query->uniqueVisitor();

            if ($period) {
                $query->withinPeriod($period);
            }
        }])->orderBy('views_count', $direction);
    }
}
