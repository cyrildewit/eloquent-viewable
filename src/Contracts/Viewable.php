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
     * Get the total number of unique views.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return int
     */
    public function getUniqueViews($period = null) : int;

    /**
     * Store a new view.
     *
     * @return bool
     */
    public function addView() : bool;

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
