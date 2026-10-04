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
     * @return array<string, int> the rows dropped, by tier
     */
    public function handle(int $chunk, bool $dryRun = false): array
    {
        $snapshot = $this->state->snapshot();
        $now = CarbonImmutable::now();
        $zone = $this->policy->timezone;
        $dropped = [];

        foreach ($this->policy->tiers() as $tier) {
            $keep = $this->policy->keep($tier);

            if (! $keep instanceof Duration) {
                continue;
            }

            $cutoff = CarbonImmutable::instance($keep->before($now));
            $coarser = $this->policy->coarserThan($tier);

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

            $dropped[$tier->value] = $dryRun ? $this->expired($tier, $cutoff)->count() : $this->drop($tier, $cutoff, $chunk);

            if (! $dryRun) {
                $this->state->putSince($tier, $cutoff);
            }
        }

        return $dropped;
    }

    private function drop(Tier $tier, CarbonImmutable $cutoff, int $chunk): int
    {
        $dropped = 0;

        do {
            $ids = $this->expired($tier, $cutoff)->limit($chunk)->pluck('id')->all();

            if ($ids !== []) {
                $dropped += $this->rollup->newQuery()->toBase()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === $chunk);

        return $dropped;
    }

    private function expired(Tier $tier, CarbonImmutable $cutoff): Builder
    {
        return $this->rollup->newQuery()->toBase()
            ->where('rollup', RollupState::ROLLUP)
            ->where('tier', $tier->value)
            ->where('bucket_start', '<', $cutoff);
    }
}
