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
    /** @return MorphMany<View, $this&Model> */
    public function views(): MorphMany;

    /**
     * @param  Builder<static&Model>  $query
     * @param  'asc'|'desc'  $direction
     * @param  array<string, string|list<string>>  $dimensions  one value or a list of values per dimension
     * @return Builder<static&Model>
     */
    public function scopeOrderByViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        bool $unique = false,
        string $as = 'views_count',
        array $dimensions = [],
    ): Builder;

    /**
     * @param  Builder<static&Model>  $query
     * @param  'asc'|'desc'  $direction
     * @param  array<string, string|list<string>>  $dimensions  one value or a list of values per dimension
     * @return Builder<static&Model>
     */
    public function scopeOrderByUniqueViews(
        Builder $query,
        string $direction = 'desc',
        ?Period $period = null,
        ?string $collection = null,
        string $as = 'unique_views_count',
        array $dimensions = [],
    ): Builder;

    /** A soft delete keeps the views, whatever this returns. */
    public function shouldRemoveViewsOnDelete(): bool;
}
