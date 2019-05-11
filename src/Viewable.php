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

/**
 * @method static self|Builder orderByViews(string $direction = 'desc', ?Period $period = null)
 * @method static self|Builder orderByUniqueViews(string $direction = 'desc', ?Period $period = null)
 **/
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
        $viewable = $query->getModel();
        $viewModel = app(ViewContract::class);

        $viewableTable = $viewable->getTable();
        $viewsTable = $viewModel->getTable();

        $query->leftJoin($viewsTable, function($join) use ($viewsTable, $viewableTable, $viewable) {
            $join->on("{$viewsTable}.viewable_id", '=', "{$viewableTable}.{$viewable->getKeyName()}");
            $join->on("{$viewsTable}.viewable_type", '=', "{$viewable->getMorphClass()}");
        });

        $query->selectRaw("{$viewable->getConnection()->getTablePrefix()}{$viewableTable}.*, count(visitor) as views_count");

        if($period) {
            $startDateTime = $period->getStartDateTime();
            $endDateTime = $period->getEndDateTime();

            if ($startDateTime && ! $endDateTime) {
                $query->where("{$viewsTable}.viewed_at", '>=', $startDateTime);
            } elseif (! $startDateTime && $endDateTime) {
                $query->where("{$viewsTable}.viewed_at", '<=', $endDateTime);
            } elseif ($startDateTime && $endDateTime) {
                $query->whereBetween("{$viewsTable}.viewed_at", [$startDateTime, $endDateTime]);
            }
        }

        return $query->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->orderBy('views_count', $direction);
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
        $viewable = $query->getModel();
        $viewModel = app(ViewContract::class);

        $viewableTable = $viewable->getTable();
        $viewsTable = $viewModel->getTable();

        $query->leftJoin($viewsTable, function($join) use ($viewsTable, $viewableTable, $viewable) {
            $join->on("{$viewsTable}.viewable_id", '=', "{$viewableTable}.{$viewable->getKeyName()}");
            $join->where("{$viewsTable}.viewable_type", '=', "{$viewable->getMorphClass()}");
        });

        $query->selectRaw("{$viewable->getConnection()->getTablePrefix()}{$viewableTable}.*, count(distinct visitor) as views_count");

        if($period) {
            $startDateTime = $period->getStartDateTime();
            $endDateTime = $period->getEndDateTime();

            if ($startDateTime && ! $endDateTime) {
                $query->where("{$viewsTable}.viewed_at", '>=', $startDateTime);
            } elseif (! $startDateTime && $endDateTime) {
                $query->where("{$viewsTable}.viewed_at", '<=', $endDateTime);
            } elseif ($startDateTime && $endDateTime) {
                $query->whereBetween("{$viewsTable}.viewed_at", [$startDateTime, $endDateTime]);
            }
        }

        return $query->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->orderBy('views_count', $direction);
    }
}
