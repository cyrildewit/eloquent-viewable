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
use CyrildeWit\EloquentViewable\Contracts\View;

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
     * Return an instance of the Views class.
     *
     * @return \CyrildeWit\EloquentViewable\Views
     */
    public function views(): Views
    {
        return views($this);
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
        $viewModel = app(View::class);

        return $query->leftJoin($viewModel->getTable(), "{$viewModel->getTable()}.viewable_id", '=', "{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->selectRaw("{$viewable->getConnection()->getTablePrefix()}{$viewable->getTable()}.*, count(`{$viewModel->getConnection()->getTablePrefix()}{$viewModel->getTable()}`.`{$viewModel->getKeyName()}`) as views_count")
            ->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
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
        $viewModel = app(View::class);

       return $query->leftJoin($viewModel->getTable(), "{$viewModel->getTable()}.viewable_id", '=', "{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->selectRaw("{$viewable->getConnection()->getTablePrefix()}{$viewable->getTable()}.*, count(distinct visitor) as views_count")
            ->groupBy("{$viewable->getTable()}.{$viewable->getKeyName()}")
            ->orderBy('views_count', $direction);
    }
}
