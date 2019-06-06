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
use CyrildeWit\EloquentViewable\Enums\SortDirection;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;

class OrderByViewsScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $options
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $query, array $options = [])
    {
        $unique = ($options['unique'] ?? false) === true;
        $descending = ($options['descending'] ?? false) === true;
        $direction = $descending ? SortDirection::DESCENDING : SortDirection::ASCENDING;
        $period = $options['period'];
        $collection = $options['collection'];

        $viewable = $query->getModel();
        $viewModel = app(ViewContract::class);
        $viewableTable = $viewable->getTable();
        $viewsTable = $viewModel->getTable();
        $distinctQuery = '';

        $query->leftJoin($viewsTable, function ($join) use ($viewsTable, $viewableTable, $viewable, $collection) {
            $join->on("{$viewsTable}.viewable_id", '=', "{$viewableTable}.{$viewable->getKeyName()}");
            $join->where("{$viewsTable}.viewable_type", '=', "{$viewable->getMorphClass()}");

            if ($collection) {
                $join->where("{$viewsTable}.collection", '=', $collection);
            }
        });

        if ($unique) {
            $distinctQuery = 'distinct ';
        }

        $query->selectRaw("{$viewable->getConnection()->getTablePrefix()}{$viewableTable}.*, count({$distinctQuery}visitor) as views_count");

        if ($period) {
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
