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

namespace CyrildeWit\EloquentViewable\Contracts\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Interface Viewable.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface Viewable
{
    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey();

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass();

    /**
     * Get a collection of all the views the model has.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function views(): MorphMany;

    /**
     * Get the total number of views.
     *
     * @return int
     */
    public function getViews(): int;

    /**
     * Get the total number of views since the given date.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @return int
     */
    public function getViewsSince($sinceDateTime): int;

    /**
     * Get the total number of views upto the given date.
     *
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getViewsUpto($uptoDateTime): int;

    /**
     * Get the total number of views between the given dates.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getViewsBetween($sinceDateTime, $uptoDateTime): int;

    /**
     * Get the total number of unique views.
     *
     * @return int
     */
    public function getUniqueViews(): int;

    /**
     * Get the total number of unique views since the given date.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @return int
     */
    public function getUniqueViewsSince($sinceDateTime): int;

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsUpto($uptoDateTime): int;

    /**
     * Get the total number of unique views upto the given date.
     *
     * @param  \Carbon\Carbon  $sinceDateTime
     * @param  \Carbon\Carbon  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsBetween($sinceDateTime, $uptoDateTime): int;

    /**
     * Get the total number of views in the past 'n' seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    public function getViewsOfPastSeconds(int $seconds): int;

    /**
     * Get the total number of views in the past 'n' minutes.
     *
     * @param  int  $minutes
     * @return int
     */
    public function getViewsOfPastMinutes(int $minutes): int;

    /**
     * Get the total number of views in the past 'n' days.
     *
     * @param  int  $days
     * @return int
     */
    public function getViewsOfPastDays(int $days): int;

    /**
     * Get the total number of views in the past 'n' weeks.
     *
     * @param  int  $weeks
     * @return int
     */
    public function getViewsOfPastWeeks(int $weeks): int;

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @param  int  $months
     * @return int
     */
    public function getViewsOfPastMonths(int $months): int;

    /**
     * Get the total number of views in the past 'n' years.
     *
     * @param  int  $years
     * @return int
     */
    public function getViewsOfPastYears(int $years): int;

    /**
     * Get the total number of views in the past 'n' seconds.
     *
     * @param  int  $seconds
     * @return int
     */
    public function getUniqueViewsOfPastSeconds(int $seconds): int;

    /**
     * Get the total number of views in the past 'n' minutes.
     *
     * @param  int  $minutes
     * @return int
     */
    public function getUniqueViewsOfPastMinutes(int $minutes): int;

    /**
     * Get the total number of views in the past 'n' days.
     *
     * @param  int  $days
     * @return int
     */
    public function getUniqueViewsOfPastDays(int $days): int;

    /**
     * Get the total number of views in the past 'n' weeks.
     *
     * @param  int  $weeks
     * @return int
     */
    public function getUniqueViewsOfPastWeeks(int $weeks): int;

    /**
     * Get the total number of views in the past 'n' months.
     *
     * @param  int  $months
     * @return int
     */
    public function getUniqueViewsOfPastMonths(int $months): int;

    /**
     * Get the total number of views in the past 'n' years.
     *
     * @param  int  $years
     * @return int
     */
    public function getUniqueViewsOfPastYears(int $years): int;

    /**
     * Store a new view.
     *
     * @return bool
     */
    public function addView(): bool;

    /**
     * Get the total number of views.
     *
     * @return void
     */
    public function removeViews();

    /**
     * Retrieve records sorted by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByViewsCount(Builder $query, string $direction = 'desc'): Builder;
}
