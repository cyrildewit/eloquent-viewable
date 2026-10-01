<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin Model
 */
interface View
{
    /**
     * Get the viewable model to which this View belongs.
     *
     * @return MorphTo<Model, Model>
     */
    public function viewable(): MorphTo;

    /**
     * Build a query for the views of the viewable that match the views query.
     *
     * @return Builder<covariant Model>
     */
    public function newQueryFor(Viewable $viewable, ViewsQuery $viewsQuery): Builder;

    /**
     * Scope a query to only include views within the period.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWithinPeriod(Builder $query, Period $period): void;

    /**
     * Scope a query to only include views within the collection.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeCollection(Builder $query, ?string $collection = null): void;
}
