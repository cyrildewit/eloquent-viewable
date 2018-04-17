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

namespace CyrildeWit\EloquentViewable\Contracts;

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
     * @param  \DateTime  $sinceDateTime
     * @param  \DateTime  $uptoDateTime
     * @param  bool  $unique
     * @return int
     */
    public function getViewsCount($viewable, $period = null, bool $unique = false);

    /**
     * Get the unique views count based upon the given arguments.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $viewable
     * @param  \DateTime|null  $sinceDateTime
     * @param  \DateTime|null  $uptoDateTime
     * @return int
     */
    public function getUniqueViewsCount($viewable, $period = null): int;

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
