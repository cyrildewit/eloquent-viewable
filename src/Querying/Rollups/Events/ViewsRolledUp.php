<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;

class ViewsRolledUp
{
    public function __construct(
        public string $rollup,
        public Tier $tier,
        public ?CarbonImmutable $from,
        public CarbonImmutable $until,
        public int $buckets,
        public bool $stopped = false,
    ) {}
}
