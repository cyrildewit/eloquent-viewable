<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;

/**
 * Every tier of every rollup holds the cutoff back, because every tier keeps
 * unique visitors and a custom rollup may keep a value from `context`. So do
 * the views before where a rollup's rows start that it should count, until a
 * refold has folded them.
 */
final readonly class RollupWatermarks implements Watermarks
{
    public function __construct(
        private RollupPolicy $policy,
        private RollupState $state,
        private UnfoldedViews $unfolded,
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

            $cutoff = $this->beforeUnfolded($definition, $snapshot, $cutoff);
        }

        return $cutoff;
    }

    public function afterFolding(): self
    {
        return new self($this->policy, $this->state, $this->unfolded, afterFolding: true);
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

    /**
     * The cutoff falls on the bucket of the coarsest tier that holds the
     * oldest unfolded view, so anonymising leaves that bucket whole for the
     * refold that fills it.
     */
    private function beforeUnfolded(RollupDefinition $definition, Snapshot $snapshot, CarbonInterface $cutoff): CarbonInterface
    {
        $unfolded = $this->unfolded->oldest($definition, $snapshot, $cutoff);
        $coarsest = $definition->tiers()[0] ?? null;

        if (! $unfolded instanceof CarbonImmutable || ! $coarsest instanceof Tier) {
            return $cutoff;
        }

        return $coarsest->floor($unfolded, $this->policy->timezone);
    }

    private function nothingFolded(CarbonInterface $cutoff): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(0, $cutoff->getTimezone());
    }
}
