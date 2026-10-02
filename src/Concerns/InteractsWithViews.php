<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Concerns;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Scopes\OrderByViews;
use CyrildeWit\EloquentViewable\Querying\Scopes\WithViewsCount;
use CyrildeWit\EloquentViewable\Recording\Observers\ViewableObserver;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method static Builder<static> orderByViews(string $direction = 'desc', ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count')
 * @method static Builder<static> orderByUniqueViews(string $direction = 'desc', ?Period $period = null, ?string $collection = null, string $as = 'unique_views_count')
 * @method static Builder<static> withViewsCount(?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count')
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
        return $query->tap(new OrderByViews($this->viewSource(), new ViewsQuery($period, $collection, $unique), $direction, $as));
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
        return $query->tap(new WithViewsCount($this->viewSource(), new ViewsQuery($period, $collection, $unique), $as));
    }

    protected function viewSource(): ViewSource
    {
        return Container::getInstance()->make(ViewSource::class);
    }
}
