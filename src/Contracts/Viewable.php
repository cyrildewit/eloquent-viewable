<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
interface Viewable
{
    /**
     * Get the views the model has.
     *
     * @return MorphMany<View, $this&Model>
     */
    public function views(): MorphMany;

    /**
     * Scope a query to order records by views count.
     *
     * @param  Builder<static&Model>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<static&Model>
     */
    public function scopeOrderByViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        bool $unique = false,
        string $as = 'views_count'
    ): Builder;

    /**
     * Scope a query to order records by unique views count.
     *
     * @param  Builder<static&Model>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<static&Model>
     */
    public function scopeOrderByUniqueViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        string $as = 'unique_views_count'
    ): Builder;

    /** Soft deletes keep the views regardless. */
    public function shouldRemoveViewsOnDelete(): bool;
}
