<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implement this to narrow the views a count reads, the way a custom rollup
 * was folded, so the views table and the rollups count the same views.
 */
interface FiltersViews
{
    /**
     * The name identifies the filter in cache keys.
     */
    public function name(): string;

    /** @param  Builder<View>  $views */
    public function filter(Builder $views): void;
}
