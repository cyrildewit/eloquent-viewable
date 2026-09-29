<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Expression;

/**
 * @method static self|Builder<Model> orderByViews(string $direction = 'desc', $period = null, string $collection = null, bool $unique = false, $as = 'views_count')
 * @method static self|Builder<Model> orderByUniqueViews(string $direction = 'desc', $period = null, string $collection = null, string $as = 'unique_views_count')
 **/
trait InteractsWithViews
{
    public static function bootInteractsWithViews(): void
    {
        static::whenBooted(fn () => static::observe(ViewableObserver::class));
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
        ?string $collection = null,
        string $as = 'unique_views_count'
    ): Builder {
        return $query->orderByViews($direction, $period, $collection, true, $as);
    }

    /**
     * Scope a query to get the views count without loading them.
     */
    public function scopeWithViewsCount(Builder $query, ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count'): Builder
    {
        // Laravel keeps only the first select column of a withCount constraint,
        // so the distinct count has to be passed as the aggregate expression
        // rather than selected inside the closure.
        $column = $unique
            ? new Expression('distinct '.$query->getQuery()->getGrammar()->wrap($this->views()->getRelated()->qualifyColumn('visitor')))
            : '*';

        return $query->withAggregate(["views as {$as}" => function (Builder $query) use ($period, $collection): void {
            if ($period instanceof Period) {
                $query->withinPeriod($period);
            }

            if ($collection) {
                $query->collection($collection);
            }
        }], $column, 'count');
    }
}
