<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use Illuminate\Database\Eloquent\Model;

/**
 * The options a count is read with.
 *
 * The timezone has two uses, so one `timezone()` call covers both. Bucket
 * boundaries in a count by interval follow its wall clock, and a relative
 * period built without a timezone of its own is re-anchored on it. A plain
 * count does not use the timezone, because a period is a pair of instants.
 *
 * The filter and every dimension filter narrow the views counted.
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
