<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implement this to narrow the views a count reads. A custom rollup
 * implements it too: the rollup folds only the views its filter keeps, and a
 * count reads the views table through the same filter, so both count the
 * same views.
 */
interface FiltersViews
{
    /**
     * The name is part of the cache key and selects the rollup that answers
     * the count. Two filters with the same name share cached counts.
     */
    public function name(): string;

    /** @param  Builder<View>  $views */
    public function filter(Builder $views): void;
}
