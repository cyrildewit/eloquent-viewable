<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Contracts;

use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface View
{
    /**
     * Get the viewable model to which this View belongs.
     *
     * @return MorphTo<Model, Model>
     */
    public function viewable(): MorphTo;

    /**
     * Scope a query to only include views within the period.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWithinPeriod(Builder $query, Period $period): void;
}
