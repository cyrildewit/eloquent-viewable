<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * Narrows a count to the views whose dimension holds one of the values. The
 * target is the column, or the JSON path, the value is kept in.
 */
final readonly class DimensionFilter
{
    /** @param  list<string>  $values */
    public function __construct(
        public string $name,
        public string $target,
        public array $values,
    ) {}

    /**
     * The keys below `context` that hold the value, or an empty list for a
     * column.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        if (! str_starts_with($this->target, 'context->')) {
            return [];
        }

        return array_slice(explode('->', $this->target), 1);
    }

    /**
     * Identifies the filter in a cache key, the same for the same values in
     * any order.
     */
    public function signature(): string
    {
        $values = $this->values;

        sort($values, SORT_STRING);

        return serialize([$this->name, $values]);
    }
}
