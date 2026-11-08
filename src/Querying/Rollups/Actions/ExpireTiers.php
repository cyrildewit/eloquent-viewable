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
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Progress;
use Illuminate\Database\Query\Builder;

/**
 * This action drops the buckets of a tier once they are older than it is
 * kept. Only whole buckets of the next coarser tier go, and only once that
 * tier has folded them, so history loses resolution but never counts.
 *
 * A tier a rollup no longer keeps is dropped whole, together with its marks,
 * because no read or watermark looks at it any more and a tier added back
 * would otherwise carry on from where it was last folded, leaving a gap.
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

    /** @return list<array{rollup: string, tier: Tier, rows: int, stopped: bool, removed: bool}> */
    public function handle(int $chunk, bool $dryRun = false, ?Deadline $deadline = null): array
    {
        $deadline ??= Deadline::none();
        $now = CarbonImmutable::now();
        $dropped = [];

        foreach ($this->policy->definitions() as $definition) {
            $snapshot = $this->state->snapshot($definition->name);

            foreach ($this->removedTiers($definition, $snapshot) as $tier) {
                $rows = $this->rows($definition->name, $tier);

                if ($dryRun) {
                    $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $rows->count(), 'stopped' => false, 'removed' => true];

                    continue;
                }

                $progress = $this->drop($rows, $chunk, $deadline);

                $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $progress->count, 'stopped' => $progress->stopped, 'removed' => true];

                if ($progress->stopped) {
                    return $dropped;
                }

                $this->state->forget($definition->name, $tier);
            }

            foreach ($definition->tiers() as $tier) {
                $cutoff = $this->expiryCutoff($definition, $tier, $snapshot, $now);

                if (! $cutoff instanceof CarbonImmutable) {
                    continue;
                }

                if ($dryRun) {
                    $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $this->expired($definition->name, $tier, $cutoff)->count(), 'stopped' => false, 'removed' => false];

                    continue;
                }

                $progress = $this->drop($this->expired($definition->name, $tier, $cutoff), $chunk, $deadline);

                $dropped[] = ['rollup' => $definition->name, 'tier' => $tier, 'rows' => $progress->count, 'stopped' => $progress->stopped, 'removed' => false];

                if ($progress->stopped) {
                    return $dropped;
                }

                $this->state->putSince($definition->name, $tier, $cutoff);
            }
        }

        return $dropped;
    }

    /**
     * The moment before which the tier's buckets expire, or null while the
     * tier is kept for good, the coarser tier has not folded them yet, or
     * nothing expired since the last run.
     */
    private function expiryCutoff(RollupDefinition $definition, Tier $tier, Snapshot $snapshot, CarbonImmutable $now): ?CarbonImmutable
    {
        $kept = $definition->keptFrom($tier, $now, $this->policy->timezone);

        if (! $kept instanceof CarbonImmutable) {
            return null;
        }

        $cutoff = $this->cutoff($definition, $tier, $snapshot, $kept);
        $since = $snapshot->since($tier);

        if ($since instanceof CarbonImmutable && $cutoff instanceof CarbonImmutable && $since >= $cutoff) {
            return null;
        }

        return $cutoff;
    }

    private function cutoff(RollupDefinition $definition, Tier $tier, Snapshot $snapshot, CarbonImmutable $kept): ?CarbonImmutable
    {
        $coarser = $definition->coarserThan($tier);

        if (! $coarser instanceof Tier) {
            return $kept;
        }

        $folded = $snapshot->folded($coarser);

        if (! $folded instanceof CarbonImmutable) {
            return null;
        }

        return $coarser->floor($kept->min($folded), $this->policy->timezone);
    }

    /**
     * The tiers the rollup no longer keeps but has marks of. A fold marks a
     * tier once it has written its rows, and the marks are only forgotten once
     * every row is dropped, so a tier with rows always has a mark.
     *
     * @return list<Tier>
     */
    private function removedTiers(RollupDefinition $definition, Snapshot $snapshot): array
    {
        return array_values(array_filter(
            Tier::cases(),
            static fn (Tier $tier): bool => ! in_array($tier, $definition->tiers(), true)
                && ($snapshot->folded($tier) instanceof CarbonImmutable || $snapshot->since($tier) instanceof CarbonImmutable),
        ));
    }

    private function drop(Builder $rows, int $chunk, Deadline $deadline): Progress
    {
        return Chunks::run(
            $deadline,
            $chunk,
            fn (): array => (clone $rows)->limit($chunk)->pluck('id')->all(),
            fn (array $ids): int => $this->rollup->newQuery()->toBase()->whereIn('id', $ids)->delete(),
        );
    }

    private function expired(string $rollup, Tier $tier, CarbonImmutable $cutoff): Builder
    {
        return $this->rows($rollup, $tier)->where('bucket_start', '<', $cutoff);
    }

    private function rows(string $rollup, Tier $tier): Builder
    {
        return $this->rollup->newQuery()->toBase()
            ->where('rollup', $rollup)
            ->where('tier', $tier->value);
    }
}
