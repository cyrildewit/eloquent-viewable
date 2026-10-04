<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Snapshot;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Query\Builder;

/**
 * This action drops the buckets of a tier once they are older than it is
 * kept. Only whole buckets of the next coarser tier go, and only once that
 * tier has folded them, so history loses resolution but never counts.
 */
final readonly class ExpireTiers
{
    public function __construct(
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private RollupState $state,
    ) {}

    /**
     * It returns the rows dropped per rollup and tier, or the rows it would
     * drop on a dry run.
     *
     * @return list<array{rollup: string, tier: Tier, rows: int}>
     */
    public function handle(int $chunk, bool $dryRun = false): array
    {
        $now = CarbonImmutable::now();
        $dropped = [];

        foreach ($this->policy->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);

            foreach ($definition->tiers() as $tier) {
                $keep = $definition->keep($tier);

                if (! $keep instanceof Duration) {
                    continue;
                }

                $cutoff = $this->cutoff($definition, $tier, $snapshot, CarbonImmutable::instance($keep->before($now)));

                if (! $cutoff instanceof CarbonImmutable) {
                    continue;
                }

                $since = $snapshot->since($tier);

                if ($since instanceof CarbonImmutable && $since >= $cutoff) {
                    continue;
                }

                if ($dryRun) {
                    $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $this->expired($definition->name, $tier, $cutoff)->count()];

                    continue;
                }

                $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $this->drop($definition->name, $tier, $cutoff, $chunk)];

                $this->state->putSince($definition->name, $tier, $cutoff);
            }
        }

        return $dropped;
    }

    /**
     * The cutoff moves back onto the edge of a bucket of the next coarser
     * tier that tier has folded. It is null while that tier has folded
     * nothing.
     */
    private function cutoff(RollupDefinition $definition, Tier $tier, Snapshot $snapshot, CarbonImmutable $cutoff): ?CarbonImmutable
    {
        $zone = $this->policy->timezone;
        $coarser = $definition->coarserThan($tier);

        if (! $coarser instanceof Tier) {
            return $tier->floor($cutoff, $zone);
        }

        $folded = $snapshot->folded($coarser);

        if (! $folded instanceof CarbonImmutable) {
            return null;
        }

        return $coarser->floor($cutoff->min($folded), $zone);
    }

    private function drop(string $rollup, Tier $tier, CarbonImmutable $cutoff, int $chunk): int
    {
        $dropped = 0;

        do {
            $ids = $this->expired($rollup, $tier, $cutoff)->limit($chunk)->pluck('id')->all();

            if ($ids !== []) {
                $dropped += $this->rollup->newQuery()->toBase()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === $chunk);

        return $dropped;
    }

    private function expired(string $rollup, Tier $tier, CarbonImmutable $cutoff): Builder
    {
        return $this->rollup->newQuery()->toBase()
            ->where('rollup', $rollup)
            ->where('tier', $tier->value)
            ->where('bucket_start', '<', $cutoff);
    }
}
