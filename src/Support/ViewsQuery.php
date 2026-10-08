<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use Illuminate\Database\Eloquent\Model;

/**
 * What a count is read over. The timezone is the clock bucket boundaries are
 * aligned to when counting by interval, and a relative period built without
 * a zone of its own is re-anchored on it, so one `timezone()` call covers
 * both. A plain count ignores it, because a period is a pair of instants.
 * The filter narrows the views counted, the way a custom rollup was folded,
 * and every dimension filter narrows them further.
 */
final readonly class ViewsQuery
{
    public ?Period $period;

    /** @param  list<DimensionFilter>  $dimensions */
    public function __construct(
        ?Period $period = null,
        public ?string $collection = null,
        public bool $unique = false,
        public ?Timezone $timezone = null,
        public ?Model $viewer = null,
        public ?FiltersViews $filter = null,
        public array $dimensions = [],
    ) {
        $this->period = $timezone instanceof Timezone ? $period?->anchoredIn($timezone) : $period;
    }

    public function withPeriod(?Period $period): self
    {
        return new self($period, $this->collection, $this->unique, $this->timezone, $this->viewer, $this->filter, $this->dimensions);
    }

    public function withViewer(?Model $viewer): self
    {
        return new self($this->period, $this->collection, $this->unique, $this->timezone, $viewer, $this->filter, $this->dimensions);
    }

    public function withDimension(DimensionFilter $filter): self
    {
        return $this->withDimensions([...$this->dimensions, $filter]);
    }

    /** @param  list<DimensionFilter>  $filters */
    public function withDimensions(array $filters): self
    {
        return new self($this->period, $this->collection, $this->unique, $this->timezone, $this->viewer, $this->filter, $filters);
    }

    /**
     * The names of the dimensions the query is narrowed by, each once.
     *
     * @return list<string>
     */
    public function dimensionNames(): array
    {
        return array_values(array_unique(array_map(static fn (DimensionFilter $filter): string => $filter->name, $this->dimensions)));
    }
}
