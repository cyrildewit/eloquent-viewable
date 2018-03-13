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

use CyrildeWit\EloquentViewable\Enums\PastType;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Services\ViewableService;

/**
 * Trait Viewable.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
trait Viewable
{
    /**
     * Get a collection of all the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views(): MorphMany
    {
        return $this->morphMany(
            config('eloquent-viewable.models.view'),
            'viewable'
        );
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
     * Get the total number of views since the given date.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @return int
     */
    public function getViewsSince($sinceDateTime): int
    {
        return app(ViewableService::class)->getViewsCount($this, $sinceDateTime);
    }

    /**
     * Get the total number of views upto the given date.
     *
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getViewsUpto($uptoDateTime): int
    {
        return app(ViewableService::class)->getViewsCount($this, null, $uptoDateTime);
    }

    /**
     * Get the total number of views between the given dates.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getViewsBetween($sinceDateTime, $uptoDateTime): int
    {
        return app(ViewableService::class)->getViewsCount($this, $sinceDateTime, $uptoDateTime);
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
     * @param  \Carbon\Carbon  $sinceDateTime
     * @return int
     */
    public function getUniqueViewsSince($sinceDateTime): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this, $sinceDateTime);
    }

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsUpto($uptoDateTime): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this, null, $uptoDateTime);
    }

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsCountBetween($sinceDateTime, $uptoDateTime): int
    {
        return app(ViewableService::class)->getUniqueViewsCount($this, $sinceDateTime, $uptoDateTime);
    }

    /**
     * Get the total number of views in the past 'n' seconds.
     *
     * @return int
     */
    public function getViewsOfPastSeconds(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_SECONDS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' minutes.
     *
     * @return int
     */
    public function getViewsOfPastMinutes(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_MINUTES, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' days.
     *
     * @return int
     */
    public function getViewsOfPastDays(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_DAYS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' weeks.
     *
     * @return int
     */
    public function getViewsOfPastWeeks(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_WEEKS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @return int
     */
    public function getViewsOfPastMonths(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_MONTHS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @return int
     */
    public function getViewsOfPastYears(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getViewsCountOfPast($this, PastType::PAST_YEARS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' seconds.
     *
     * @return int
     */
    public function getUniqueViewsOfPastSeconds(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_SECONDS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' minutes.
     *
     * @return int
     */
    public function getUniqueViewsOfPastMinutes(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_MINUTES, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' days.
     *
     * @return int
     */
    public function getUniqueViewsOfPastDays(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_DAYS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' weeks.
     *
     * @return int
     */
    public function getUniqueViewsOfPastWeeks(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_WEEKS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @return int
     */
    public function getUniqueViewsOfPastMonths(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_MONTHS, $pastValue);
    }

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @return int
     */
    public function getUniqueViewsOfPastYears(int $pastValue): int
    {
        return app(ViewableService::class)
            ->getUniqueViewsCountOfPast($this, PastType::PAST_YEARS, $pastValue);
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
}
