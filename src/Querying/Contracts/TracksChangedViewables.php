<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

/**
 * A source that can say which viewables a count may have changed for, so
 * `views:recount` recounts only those. A source without it has every model
 * recounted on every run.
 */
interface TracksChangedViewables
{
    /**
     * The keys of the viewables of a type with views in `[from, until)`,
     * counting what the source keeps beyond the views table, such as the
     * buckets of a rollup. A bound is a `StateStore::Format` timestamp.
     *
     * @return list<int|string>
     */
    public function keysViewedBetween(string $type, ?string $from, ?string $until): array;

    /**
     * Whether a count changes once old views are pruned. It does not for a
     * source that folded them before, such as the rollups.
     */
    public function countsChangeWhenPruned(): bool;
}
