<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Concerns;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Scopes\OrderByViews;
use CyrildeWit\EloquentViewable\Querying\Scopes\WhereViewed;
use CyrildeWit\EloquentViewable\Querying\Scopes\WhereViewsCount;
use CyrildeWit\EloquentViewable\Querying\Scopes\WithViewsCount;
use CyrildeWit\EloquentViewable\Recording\Observers\ViewableObserver;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method static Builder<static> orderByViews(string $direction = 'desc', ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count')
 * @method static Builder<static> orderByUniqueViews(string $direction = 'desc', ?Period $period = null, ?string $collection = null, string $as = 'unique_views_count')
 * @method static Builder<static> withViewsCount(?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count')
 * @method static Builder<static> whereViewsCount(string $operator, int $count, ?Period $period = null, ?string $collection = null, bool $unique = false)
 * @method static Builder<static> whereUniqueViewsCount(string $operator, int $count, ?Period $period = null, ?string $collection = null)
 * @method static Builder<static> whereViewedBy(Model $viewer, ?Period $period = null, ?string $collection = null)
 * @method static Builder<static> whereNotViewedBy(Model $viewer, ?Period $period = null, ?string $collection = null)
 * @method static Builder<static> whereViewedByVisitor(string $visitor, ?Period $period = null, ?string $collection = null)
 * @method static Builder<static> whereNotViewedByVisitor(string $visitor, ?Period $period = null, ?string $collection = null)
 */
trait InteractsWithViews
{
    public static function bootInteractsWithViews(): void
    {
        static::whenBooted(fn () => static::observe(ViewableObserver::class));
    }

    public function shouldRemoveViewsOnDelete(): bool
    {
        return true;
    }

    /** @return MorphMany<View, $this> */
    public function views(): MorphMany
    {
        return $this->morphMany(
            Container::getInstance()->make(Config::class)->viewModel(),
            'viewable'
        );
    }

    /**
     * @param  Builder<static>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<static>
     */
    public function scopeOrderByViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        bool $unique = false,
        string $as = 'views_count'
    ): Builder {
        return $query->tap(new OrderByViews($this->subquerySource(), new ViewsQuery($period, $collection, $unique), $direction, $as));
    }

    /**
     * @param  Builder<static>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<static>
     */
    public function scopeOrderByUniqueViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        string $as = 'unique_views_count'
    ): Builder {
        return $query->orderByViews($direction, $period, $collection, true, $as);
    }

    /**
     * Select the views count as a column instead of loading the views.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithViewsCount(Builder $query, ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count'): Builder
    {
        return $query->tap(new WithViewsCount($this->subquerySource(), new ViewsQuery($period, $collection, $unique), $as));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereViewsCount(
        Builder $query,
        string $operator,
        int $count,
        ?Period $period = null,
        ?string $collection = null,
        bool $unique = false
    ): Builder {
        return $query->tap(new WhereViewsCount($this->subquerySource(), new ViewsQuery($period, $collection, $unique), $operator, $count));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereUniqueViewsCount(Builder $query, string $operator, int $count, ?Period $period = null, ?string $collection = null): Builder
    {
        return $query->whereViewsCount($operator, $count, $period, $collection, true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereViewedBy(Builder $query, Model $viewer, ?Period $period = null, ?string $collection = null): Builder
    {
        return $query->tap(new WhereViewed($this->subquerySource(), new ViewsQuery($period, $collection, viewer: $viewer)));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereNotViewedBy(Builder $query, Model $viewer, ?Period $period = null, ?string $collection = null): Builder
    {
        return $query->tap(new WhereViewed($this->subquerySource(), new ViewsQuery($period, $collection, viewer: $viewer), not: true));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereViewedByVisitor(Builder $query, string $visitor, ?Period $period = null, ?string $collection = null): Builder
    {
        return $query->tap(new WhereViewed($this->subquerySource(), new ViewsQuery($period, $collection), $visitor));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereNotViewedByVisitor(Builder $query, string $visitor, ?Period $period = null, ?string $collection = null): Builder
    {
        return $query->tap(new WhereViewed($this->subquerySource(), new ViewsQuery($period, $collection), $visitor, not: true));
    }

    /** @throws UnsupportedBySource */
    protected function subquerySource(): SubquerySource
    {
        $source = Container::getInstance()->make(ViewSource::class);

        return $source instanceof SubquerySource ? $source : throw UnsupportedBySource::scopes($source);
    }
}
