<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;

/**
 * What folding one tier reports back: the run, and the lowest bucket it
 * folded, which the origin of the rollup moves back to.
 *
 * @internal
 */
final readonly class TierFold
{
    public function __construct(
        public ViewsRolledUp $run,
        public CarbonImmutable $lowest,
    ) {}
}
