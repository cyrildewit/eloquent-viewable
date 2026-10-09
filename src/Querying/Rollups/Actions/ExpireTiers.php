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
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Database\Query\Builder;

/**
 * This action drops the buckets of a tier once they are older than it is
 * kept. Only whole buckets of the next coarser tier go, and only once that
 * tier has folded them, so history loses resolution but never counts.
 *
 * Once the deadline passes, the run stops before the next chunk and leaves
 * the rest of the tier for the next run.
 */
final readonly class ExpireTiers
{
    public function __construct(
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private RollupState $state,
    ) {}

    /** @return list<array{rollup: string, tier: Tier, rows: int, stopped: bool}> */
    public function handle(int $chunk, bool $dryRun = false, ?Deadline $deadline = null): array
    {
        $deadline ??= Deadline::none();
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
                    $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $this->expired($definition->name, $tier, $cutoff)->count(), 'stopped' => false];

                    continue;
                }

                $result = $this->drop($definition->name, $tier, $cutoff, $chunk, $deadline);

                $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, ...$result];

                if ($result['stopped']) {
                    return $dropped;
                }

                $this->state->putSince($definition->name, $tier, $cutoff);
            }
        }

        return $dropped;
    }

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

    /** @return array{rows: int, stopped: bool} */
    private function drop(string $rollup, Tier $tier, CarbonImmutable $cutoff, int $chunk, Deadline $deadline): array
    {
        $dropped = 0;

        do {
            if ($deadline->passed()) {
                return ['rows' => $dropped, 'stopped' => true];
            }

            $ids = $this->expired($rollup, $tier, $cutoff)->limit($chunk)->pluck('id')->all();

            if ($ids !== []) {
                $dropped += $this->rollup->newQuery()->toBase()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === $chunk);

        return ['rows' => $dropped, 'stopped' => false];
    }

    private function expired(string $rollup, Tier $tier, CarbonImmutable $cutoff): Builder
    {
        return $this->rollup->newQuery()->toBase()
            ->where('rollup', $rollup)
            ->where('tier', $tier->value)
            ->where('bucket_start', '<', $cutoff);
    }
}
