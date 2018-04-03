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

namespace CyrildeWit\EloquentViewable\Traits;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Services\ViewableService;
use CyrildeWit\EloquentViewable\Observers\ViewableObserver;
use CyrildeWit\EloquentViewable\Contracts\Models\View as ViewContract;

/**
 * Trait Viewable.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
trait Viewable
{
    /**
     * Boot the Viewable trait for a model.
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views(): MorphMany
    {
        return $this->morphMany(app(ViewContract::class), 'viewable');
    }

    /**
     * Get the total number of views.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getViews($period = null): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, $period);
    }

    /**
     * Get the total number of unique views.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getUniqueViews($period = null) : int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, $period);
    }

    /**
     * Store a new view.
     *
     * @return bool
     */
    public function addView(): bool
    {
        return app(ViewableService::class)->addViewTo($this);
    }

    /**
     * Get the total number of views.
     *
     * @return void
     */
    public function removeViews()
    {
        app(ViewableService::class)->removeModelViews($this);
    }

    /**
     * Retrieve records sorted by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByViewsCount(Builder $query, string $direction = 'desc'): Builder
    {
        return app(ViewableService::class)->applyScopeOrderByViewsCount($query, $direction);
    }
}
