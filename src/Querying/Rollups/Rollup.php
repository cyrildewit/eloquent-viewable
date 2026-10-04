<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Models\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * A rollup of your own: the views a filter keeps, folded into the tiers and
 * groupings it names, and per value of at most one dimension. Listed under
 * `retention.rollups.custom` and read with `views($post)->rollup($name)`.
 *
 * The views table is read through the same filter, so recent and old counts
 * agree. It is also the only way a value from `context` outlives
 * anonymising, so anonymising waits for every custom rollup too.
 */
abstract class Rollup implements FiltersViews
{
    /**
     * Stored with every row and named in `rollup()`. Letters, digits, `-`
     * and `_`; `views` is taken by the built-in rollup.
     */
    public string $name = '';

    /**
     * The tiers to keep, each with how long it is kept, or null for forever.
     *
     * @return array<string, string|null>
     */
    abstract public function tiers(): array;

    /**
     * The groupings to keep, from `viewable`, `viewable_collection`, `type`
     * and `type_collection`.
     *
     * @return list<string>
     */
    public function groupings(): array
    {
        return ['viewable', 'type'];
    }

    /** @param  Builder<View>  $views */
    public function filter(Builder $views): void {}

    /**
     * A column or JSON path such as `context->campaign`, counted per value.
     * Keep it to a handful of values: every value is a row per bucket.
     */
    public function dimension(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return $this->name;
    }
}
