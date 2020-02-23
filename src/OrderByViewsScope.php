<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Enums\SortDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
        $period = $options['period'] ?? null;
        $collection = $options['collection'] ?? null;

        $query->withCount(['views as views_count' => function (Builder $query) use ($unique, $direction, $period, $collection) {
            if ($period) {
                $query->withinPeriod($period);
            }

            if ($collection) {
                $query->collection($collection);
            }

            if ($unique) {
                $query->select(DB::raw('count(DISTINCT visitor)'));
            }
        }]);

        return $query->orderBy('views_count', $direction);
    }
}
