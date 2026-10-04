<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;

/**
 * Every tier keeps unique visitors, and a custom rollup may keep a value
 * from `context`, so nothing is anonymised or deleted before every tier of
 * every rollup has folded it.
 */
final readonly class RollupWatermarks implements Watermarks
{
    public function __construct(
        private RollupPolicy $policy,
        private RollupState $state,
    ) {}

    public function clamp(CarbonInterface $cutoff): CarbonInterface
    {
        foreach ($this->policy->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);

            foreach ($definition->tiers() as $tier) {
                // A tier that has never been folded has captured nothing yet.
                $folded = $snapshot->folded($tier) ?? CarbonImmutable::createFromTimestamp(0, $cutoff->getTimezone());

                if ($folded < $cutoff) {
                    $cutoff = $folded;
                }
            }
        }

        return $cutoff;
    }
}
