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

namespace CyrildeWit\EloquentViewable\Contracts\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface ViewableService.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
interface ViewableService
{
    /**
     * Get the views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCount($viewable, $sinceDateTime = null, $uptoDateTime = null, bool $unique = false): int;

    /**
     * Get the unique views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \Carbon\Carbon|null  $sinceDateTime
     * @param  \Carbon\Carbon|null  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsCount($viewable, $sinceDateTime = null, $uptoDateTime = null): int;

    /**
     * Get the views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $pastType
     * @param  int  $pastValue
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCountOfPast($viewable, $pastType, int $pastValue, bool $unique = false): int;

    /**
     * Get the unique views count of the past period.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  string  $pastType
     * @param  int  $pastValue
     * @return int
     */
    public function getUniqueViewsCountOfPast($viewable, $pastType, int $pastValue);

    /**
     * Store a new view.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return bool
     */
    public function addViewTo($viewable): bool;

    /**
     * Remove all views from a viewable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @return void
     */
    public function removeModelViews($viewable);

    /**
     * Retrieve records sorted by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyScopeOrderByViewsCount(Builder $query, string $direction = 'desc'): Builder;
}
