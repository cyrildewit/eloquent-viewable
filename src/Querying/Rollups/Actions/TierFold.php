<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;

/**
 * What folding one tier reports back: the run, the lowest bucket it folded,
 * where the rows of the tier start, and the bucket of the first view it saw,
 * which the origin of the rollup moves back to. The two differ when the tier
 * keeps only its recent buckets.
 *
 * @internal
 */
final readonly class TierFold
{
    public function __construct(
        public ViewsRolledUp $run,
        public CarbonImmutable $lowest,
        public CarbonImmutable $origin,
    ) {}
}
