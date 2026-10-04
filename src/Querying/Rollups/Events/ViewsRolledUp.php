<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;

/**
 * Dispatched once a tier is folded up to `until`, `from` being where it stood
 * before, null on the first run. The buckets count those folded again for
 * views that landed late.
 */
class ViewsRolledUp
{
    public function __construct(
        public Tier $tier,
        public ?CarbonImmutable $from,
        public CarbonImmutable $until,
        public int $buckets,
    ) {}
}
