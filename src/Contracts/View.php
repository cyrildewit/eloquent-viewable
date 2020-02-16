<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface View
{
    /**
     * Get the viewable model to which this View belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function viewable(): MorphTo;

    /**
     * Scope a query to only include views within the period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  mixed $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithinPeriod(Builder $query, Period $period);

    /**
     * Scope a query to only include unique views.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUniqueVisitor(Builder $query);
}
