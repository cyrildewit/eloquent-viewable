<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * @method static self|Builder orderByViews(string $direction = 'desc', $period = null, string $collection = null, bool $unique = false, $as = 'views_count')
 * @method static self|Builder orderByUniqueViews(string $direction = 'desc', $period = null, string $collection = null, string $as = 'unique_views_count')
 **/
trait InteractsWithViews
{
    /**
     * Viewable boot logic.
     *
     * @return void
     */
    public static function bootInteractsWithViews()
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
        return $this->morphMany(
            Container::getInstance()->make(ViewContract::class),
            'viewable'
        );
    }

    /**
     * Scope a query to order records by views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @param  bool  $unique
     * @param  string  $as
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByViews(
        Builder $query,
        string $direction = 'desc',
        $period = null,
        string $collection = null,
        bool $unique = false,
        string $as = 'views_count'
    ): Builder {
        return $query->withViewsCount($period, $collection, $unique, $as)
            ->orderBy($as, $direction);
    }

    /**
     * Scope a query to order records by unique views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @param  string  $collection
     * @param  string  $as
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByUniqueViews(
        Builder $query,
        string $direction = 'desc',
        $period = null,
        string $collection = null,
        string $as = 'unique_views_count'
    ): Builder {
        return $query->orderByViews($direction, $period, $collection, true, $as);
    }

    /**
     * Scope a query to order records by unique views count.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $direction
     * @param  \CyrildeWit\EloquentViewable\Support\Period|null  $period
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithViewsCount(Builder $query, $period = null, string $collection = null, bool $unique = false, string $as = 'views_count'): Builder
    {
        return $query->withCount(["views as ${as}" => function (Builder $query) use ($period, $collection, $unique) {
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
    }
}
