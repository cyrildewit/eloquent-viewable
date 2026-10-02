<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Data;

/**
 * A stretch of stored wall clock over which one fixed offset turns the
 * storage clock into the target clock.
 */
final readonly class OffsetSegment
{
    public function __construct(
        /** As `Y-m-d H:i:s`, or null for the first segment, which has no lower bound. */
        public ?string $startsAt,
        /** In seconds. */
        public int $offset,
    ) {}
}
