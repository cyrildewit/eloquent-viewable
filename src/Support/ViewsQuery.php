<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

/**
 * What a count is read over. The timezone is the clock bucket boundaries are
 * aligned to when counting by interval, and a relative period built without
 * a zone of its own is re-anchored on it, so one `timezone()` call covers
 * both. A plain count ignores it, because a period is a pair of instants.
 */
final readonly class ViewsQuery
{
    public ?Period $period;

    public function __construct(
        ?Period $period = null,
        public ?string $collection = null,
        public bool $unique = false,
        public ?Timezone $timezone = null,
    ) {
        $this->period = $timezone instanceof Timezone ? $period?->anchoredIn($timezone) : $period;
    }
}
