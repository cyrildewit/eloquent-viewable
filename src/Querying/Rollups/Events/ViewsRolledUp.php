<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;

/**
 * Dispatched once a tier of the named rollup is folded up to `until`. `from`
 * is where the tier stood before the run, or null on the first run.
 * `buckets` counts every bucket the run folded, including the buckets folded
 * again because views landed late. `stopped` is true when the run reached its
 * deadline, and `until` is then as far as it got.
 */
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
