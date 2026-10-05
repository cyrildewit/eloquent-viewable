<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Data;

use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;

/**
 * What one maintenance run did, step by step. A step that is not configured,
 * or that the run did not reach, is empty. A run that reached its deadline is
 * stopped, and the next run carries on where it left off.
 */
final readonly class MaintenanceRun
{
    /**
     * @param  list<ViewsRolledUp>  $folded
     * @param  list<array{rollup: string, tier: Tier, rows: int, stopped: bool}>  $expired
     */
    public function __construct(
        public array $folded = [],
        public array $expired = [],
        public ?RetentionRun $anonymised = null,
        public ?RetentionRun $pruned = null,
        public ?RecountRun $recounted = null,
        public bool $stopped = false,
    ) {}
}
