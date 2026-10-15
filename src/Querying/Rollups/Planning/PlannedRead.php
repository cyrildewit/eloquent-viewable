<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;

/**
 * A plan with the rollup whose rows answer its rollup segments.
 *
 * @internal
 */
final readonly class PlannedRead
{
    public function __construct(
        public Plan $plan,
        public RollupDefinition $definition,
    ) {}
}
