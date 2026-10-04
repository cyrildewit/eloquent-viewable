<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Query\Builder;

/**
 * Drops the buckets of a tier once they are older than it is kept. Only whole
 * buckets of the next coarser tier go, and only once that tier has folded
 * them, so history loses resolution but never counts.
 */
final readonly class ExpireTiers
{
    public function __construct(
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private RollupState $state,
    ) {}

    /**
     * @return list<array{rollup: string, tier: Tier, rows: int}> the rows dropped
     */
    public function handle(int $chunk, bool $dryRun = false): array
    {
        $now = CarbonImmutable::now();
        $zone = $this->policy->timezone;
        $dropped = [];

        foreach ($this->policy->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);

            foreach ($definition->tiers() as $tier) {
                $keep = $definition->keep($tier);

                if (! $keep instanceof Duration) {
                    continue;
                }

                $cutoff = CarbonImmutable::instance($keep->before($now));
                $coarser = $definition->coarserThan($tier);

                if ($coarser instanceof Tier) {
                    $captured = $snapshot->folded($coarser);

                    if (! $captured instanceof CarbonImmutable) {
                        continue;
                    }

                    $cutoff = $coarser->floor($cutoff->min($captured), $zone);
                } else {
                    $cutoff = $tier->floor($cutoff, $zone);
                }

                $since = $snapshot->since($tier);

                if ($since instanceof CarbonImmutable && $since >= $cutoff) {
                    continue;
                }

                $rows = $dryRun ? $this->expired($definition->name, $tier, $cutoff)->count() : $this->drop($definition->name, $tier, $cutoff, $chunk);
                $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $rows];

                if (! $dryRun) {
                    $this->state->putSince($definition->name, $tier, $cutoff);
                }
            }
        }

        return $dropped;
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
