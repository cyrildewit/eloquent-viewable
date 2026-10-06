<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use Carbon\CarbonImmutable;

/**
 * What the Redis stream holds that has not landed in the views table yet.
 */
class StreamBacklog
{
    public function __construct(
        public int $length,
        public int $pending,
        public int $stalled,
        public ?CarbonImmutable $oldestAt,
    ) {}
}
