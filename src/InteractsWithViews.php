<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Support\Period;
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
     */
    public function scopeOrderByViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        bool $unique = false,
        string $as = 'views_count'
    ): Builder {
        return $query->withViewsCount($period, $collection, $unique, $as)
            ->orderBy($as, $direction);
    }

    /**
     * Scope a query to order records by unique views count.
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
     * Scope a query to get the views count without loading them.
     */
    public function scopeWithViewsCount(Builder $query, ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count'): Builder
    {
        return $query->withCount(["views as {$as}" => function (Builder $query) use ($period, $collection, $unique) {
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
