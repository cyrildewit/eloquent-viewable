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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Enums\PastType;
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
     * @return int
     */
    public function getViews(): int
    {
        return app(ViewableService::class)->getViewsCount($this);
    }

    /**
     * Get the total number of views since the given date time.
     *
     * @param  \DateTime  $sinceDateTime
     * @return int
     */
    public function getViewsSince(DateTime $sinceDateTime): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::create($sinceDateTime));
    }

    /**
     * Get the total number of views upto the given date time.
     *
     * @param  \DateTime  $uptoDateTime
     * @return int
     */
    public function getViewsUpto(DateTime $uptoDateTime): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::create(null, $uptoDateTime));
    }

    /**
     * Get the total number of views between the given datetimes.
     *
     * @param  \DateTime  $sinceDateTime
     * @param  \DateTime  $uptoDateTime
     * @return int
     */
    public function getViewsBetween(DateTime $sinceDateTime, DateTime $uptoDateTime): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::create($sinceDateTime, $uptoDateTime));
    }

    /**
     * Get the total number of unique views.
     *
     * @return int
     */
    public function getUniqueViews(): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this);
    }

    /**
     * Get the total number of unique views since the given date.
     *
     * @param  \DateTime  $sinceDateTime
     * @return int
     */
    public function getUniqueViewsSince(DateTime $sinceDateTime): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCount($this, Period::create($sinceDateTime));
    }

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \DateTime  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsUpto($uptoDateTime): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCount($this, null, Period::create(null, $uptoDateTime));
    }

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \DateTime  $sinceDateTime
     * @param  \DateTime  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsBetween($sinceDateTime, $uptoDateTime): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCount($this, Period::create($sinceDateTime, $uptoDateTime));
    }

    /**
     * Get the total number of views in the past days.
     *
     * @param  int  $seconds
     * @return int
     */
    public function getViewsOfPastDays(int $days): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::pastDays($days));
    }

    /**
     * Get the total number of views in the past weeks.
     *
     * @param  int  $weeks
     * @return int
     */
    public function getViewsOfPastWeeks(int $weeks): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::pastWeeks($weeks));
    }

    /**
     * Get the total number of views in the past months.
     *
     * @param  int  $months
     * @return int
     */
    public function getViewsOfPastMonths(int $months): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::pastMonths($months));
    }

    /**
     * Get the total number of views in the past years.
     *
     * @param  int  $years
     * @return int
     */
    public function getViewsOfPastYears(int $years): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::pastYears($years));
    }

    /**
     * Get the total number of views in the past 'n' seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    public function getViewsOfSubSeconds(int $seconds): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::subSeconds($seconds));
    }

    /**
     * Get the total number of views in the past 'n' days.
     *
     * @param  int  $days
     * @return int
     */
    public function getViewsOfSubDays(int $days): int
    {
        return app(ViewableService::class)
            ->getViewsCount($this, Period::subDays($days));
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
