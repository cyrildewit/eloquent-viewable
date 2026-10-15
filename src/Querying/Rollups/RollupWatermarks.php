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
        private bool $afterFolding = false,
    ) {}

    public function clamp(CarbonInterface $cutoff): CarbonInterface
    {
        $now = CarbonImmutable::now();

        foreach ($this->policy->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);

            foreach ($definition->tiers() as $tier) {
                $folded = $this->folded($snapshot, $tier, $now) ?? $this->nothingFolded($cutoff);

                if ($folded < $cutoff) {
                    $cutoff = $folded;
                }
            }
        }

        return $cutoff;
    }

    public function afterFolding(): self
    {
        return new self($this->policy, $this->state, afterFolding: true);
    }

    /**
     * A run always marks a tier folded up to where its buckets have closed,
     * even when it found no views to fold.
     */
    private function folded(Snapshot $snapshot, Tier $tier, CarbonImmutable $now): ?CarbonImmutable
    {
        $folded = $snapshot->folded($tier);

        if (! $this->afterFolding) {
            return $folded;
        }

        $closed = $this->policy->closedUntil($tier, $now);

        if (! $folded instanceof CarbonImmutable) {
            return $closed;
        }

        return $folded->max($closed);
    }

    private function nothingFolded(CarbonInterface $cutoff): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(0, $cutoff->getTimezone());
    }
}
