<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;

/**
 * Every tier of every rollup holds the cutoff back, because every tier keeps
 * unique visitors and a custom rollup may keep a value from `context`.
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
                $folded = $snapshot->folded($tier) ?? $this->nothingFolded($cutoff);

                if ($folded < $cutoff) {
                    $cutoff = $folded;
                }
            }
        }

        return $cutoff;
    }

    private function nothingFolded(CarbonInterface $cutoff): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(0, $cutoff->getTimezone());
    }
}
