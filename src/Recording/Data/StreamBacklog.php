<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use Carbon\CarbonImmutable;

/**
 * What the Redis stream holds that has not landed in the views table yet.
 */
class StreamBacklog
{
    /**
     * @param  int  $pending  entries a flush has taken but not acknowledged
     * @param  int  $stalled  pending entries idle long enough for the next flush to claim them, counted up to one page
     */
    public function __construct(
        public int $length,
        public int $pending,
        public int $stalled,
        public ?CarbonImmutable $oldestAt,
    ) {}
}
