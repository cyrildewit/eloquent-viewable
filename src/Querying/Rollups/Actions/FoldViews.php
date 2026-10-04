<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\RollupsNotInstalled;
use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Snapshot;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder;

/**
 * Folds the views of closed buckets into the rollup table, every tier from
 * the views themselves, so unique visitors are exact per bucket at every
 * grain. A bucket is folded whole: its rows are deleted and inserted again
 * in one transaction, so a rerun or a refold is safe.
 *
 * A bucket closes once `settle` has passed since its end. A view that lands
 * after its bucket was folded is found by its id, above the highest id the
 * last run saw, and its bucket is folded again.
 */
final readonly class FoldViews
{
    public function __construct(
        private View $view,
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private RollupState $state,
        private Dispatcher $events,
    ) {}

    /**
     * @param  CarbonInterface|null  $from  fold again from here instead of where the last run stopped
     * @param  string|null  $rollup  fold only the rollup of this name
     * @return list<ViewsRolledUp>
     *
     * @throws RollupsNotInstalled
     */
    public function handle(?Tier $only = null, ?CarbonInterface $from = null, bool $dryRun = false, ?string $rollup = null): array
    {
        $this->ensureInstalled();

        $now = CarbonImmutable::now();
        $closed = CarbonImmutable::instance($this->policy->settle?->before($now) ?? $now);
        $lastId = $this->state->lastId();
        $maxId = $this->maxId();
        $runs = [];

        foreach ($this->policy->definitions() as $definition) {
            if ($rollup !== null && $definition->name !== $rollup) {
                continue;
            }

            $snapshot = $this->state->snapshot($definition->name);
            $origin = $snapshot->origin;

            foreach ($definition->tiers() as $tier) {
                if ($only instanceof Tier && $tier !== $only) {
                    continue;
                }

                $run = $this->foldTier($definition, $tier, $snapshot, $closed, $from, $lastId, $maxId, $dryRun, $origin);

                if ($run->buckets > 0 && ! $dryRun) {
                    $this->events->dispatch($run);
                }

                $runs[] = $run;
            }

            if (! $dryRun && $origin instanceof CarbonImmutable) {
                $this->state->putOrigin($definition->name, $origin);
            }
        }

        // Only once every tier of every rollup has looked at the views below
        // it, or one left out would miss the late views of this run.
        if (! $only instanceof Tier && $rollup === null && $maxId !== null && ! $dryRun) {
            $this->state->putLastId($maxId);
        }

        return $runs;
    }

    /**
     * @param  CarbonImmutable|null  $origin  moved back to the lowest bucket this tier folds
     *
     * @param-out CarbonImmutable $origin
     */
    private function foldTier(RollupDefinition $definition, Tier $tier, Snapshot $snapshot, CarbonImmutable $closed, ?CarbonInterface $from, ?int $lastId, ?int $maxId, bool $dryRun, ?CarbonImmutable &$origin): ViewsRolledUp
    {
        $zone = $this->policy->timezone;
        $until = $tier->floor($closed, $zone);
        $folded = $snapshot->folded($tier);
        $buckets = 0;
        $dirty = $this->dirtyBuckets($tier, $snapshot, $lastId, $maxId);

        foreach ($dirty as $bucket) {
            $buckets += $this->foldBucket($definition, $tier, $bucket, $dryRun);
        }

        $cursor = $from instanceof CarbonInterface ? $this->refoldFrom($tier, $snapshot, $from) : $folded;

        if (! $cursor instanceof CarbonImmutable) {
            $first = $this->firstViewedAt(null, $until);
            $cursor = $first instanceof CarbonImmutable ? $tier->floor($first, $zone) : $until;
        }

        $since = $snapshot->since($tier);
        $lowest = $dirty === [] ? $cursor : $dirty[0]->min($cursor);

        if (! $dryRun && (! $since instanceof CarbonImmutable || $lowest < $since)) {
            $this->state->putSince($definition->name, $tier, $lowest);
        }

        $origin = $origin instanceof CarbonImmutable ? $origin->min($lowest) : $lowest;

        while (($first = $this->firstViewedAt($cursor, $until)) instanceof CarbonImmutable) {
            $bucket = $tier->floor($first, $zone);
            $buckets += $this->foldBucket($definition, $tier, $bucket, $dryRun);
            $cursor = $tier->next($bucket, $zone);

            if (! $dryRun && (! $folded instanceof CarbonImmutable || $cursor > $folded)) {
                $this->state->putFolded($definition->name, $tier, $cursor);
            }
        }

        if (! $dryRun && (! $folded instanceof CarbonImmutable || $until > $folded)) {
            $this->state->putFolded($definition->name, $tier, $until);
        }

        return new ViewsRolledUp($definition->name, $tier, $folded, $until->max($folded ?? $until), $buckets);
    }

    /**
     * The buckets behind the watermark that views landed in since the last
     * run, as long as their views are all still there to fold again.
     *
     * @return list<CarbonImmutable>
     */
    private function dirtyBuckets(Tier $tier, Snapshot $snapshot, ?int $lastId, ?int $maxId): array
    {
        $folded = $snapshot->folded($tier);

        if (! $folded instanceof CarbonImmutable || $lastId === null || $maxId === null || $maxId <= $lastId) {
            return [];
        }

        $moments = $this->view->newQuery()->toBase()
            ->where('id', '>', $lastId)
            ->where('id', '<=', $maxId)
            ->where('viewed_at', '<', $folded)
            ->distinct()
            ->pluck('viewed_at');

        $floor = $this->refoldFloor($tier, $snapshot);
        $buckets = [];

        foreach ($moments as $moment) {
            $bucket = $tier->floor(CarbonImmutable::parse((string) $moment), $this->policy->timezone); // @phpstan-ignore cast.string (a timestamp column)

            if (! $floor instanceof CarbonImmutable || $bucket >= $floor) {
                $buckets[$bucket->format('Y-m-d H:i:s')] = $bucket;
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Where folding again may start: no earlier than the first bucket whose
     * views are all still there.
     */
    private function refoldFrom(Tier $tier, Snapshot $snapshot, CarbonInterface $from): CarbonImmutable
    {
        $zone = $this->policy->timezone;
        $from = $tier->floor($from, $zone);
        $floor = $this->refoldFloor($tier, $snapshot);

        return $floor instanceof CarbonImmutable && $from < $floor ? $tier->ceil($floor, $zone) : $from;
    }

    /**
     * Pruned views are gone, and anonymised views only keep one visitor id
     * per day, so a bucket before either cannot be folded again without
     * losing what it holds.
     */
    private function refoldFloor(Tier $tier, Snapshot $snapshot): ?CarbonImmutable
    {
        $anonymised = $tier->isCoarserThan(Tier::Day) ? $snapshot->anonymised : null;

        if (! $anonymised instanceof CarbonImmutable) {
            return $snapshot->pruned;
        }

        return $snapshot->pruned instanceof CarbonImmutable ? $anonymised->max($snapshot->pruned) : $anonymised;
    }

    /**
     * Returns one, the bucket, so a caller can count what it folded.
     */
    private function foldBucket(RollupDefinition $definition, Tier $tier, CarbonImmutable $start, bool $dryRun): int
    {
        if ($dryRun) {
            return 1;
        }

        $end = $tier->next($start, $this->policy->timezone);

        $this->rollup->getConnection()->transaction(function () use ($definition, $tier, $start, $end): void {
            $this->rollup->newQuery()->toBase()
                ->where('rollup', $definition->name)
                ->where('tier', $tier->value)
                ->where('bucket_start', $start)
                ->delete();

            foreach ($definition->groupings as $grouping) {
                $this->insert($definition, $tier, $grouping, $start, $end, null);

                // The totals above stay, because unique visitors cannot be
                // summed from the rows per value.
                if ($definition->dimension() !== null) {
                    $this->insert($definition, $tier, $grouping, $start, $end, $definition->dimension());
                }
            }
        });

        return 1;
    }

    private function insert(RollupDefinition $definition, Tier $tier, Grouping $grouping, CarbonImmutable $start, CarbonImmutable $end, ?string $dimension): void
    {
        $views = $this->view->newQuery();
        $definition->filter($views);

        $query = $views->toBase()
            ->where('viewed_at', '>=', $start)
            ->where('viewed_at', '<', $end);

        $grammar = $query->getGrammar();
        $columns = $grouping->columns();

        // Postgres types a bound value in a select list as text, which a
        // timestamp column refuses.
        $bucket = $this->view->getConnection()->getDriverName() === 'pgsql' ? 'cast(? as timestamp)' : '?';

        $query
            ->selectRaw("?, ?, {$bucket}, ?", [$definition->name, $tier->value, $start->format('Y-m-d H:i:s'), $grouping->stored($dimension !== null)])
            ->addSelect($columns)
            ->groupBy($columns);

        if ($dimension !== null) {
            // A JSON path such as `context->campaign` compiles to the
            // driver's own extraction.
            $query->selectRaw($grammar->wrap($dimension))->groupByRaw($grammar->wrap($dimension)); // @phpstan-ignore argument.type, argument.type (a column or JSON path the application names, not user input)
        }

        $query->selectRaw("count(*), count(distinct {$grammar->wrap('visitor')})"); // @phpstan-ignore argument.type (a wrapped identifier, not user input)

        $this->rollup->newQuery()->toBase()->insertUsing(
            ['rollup', 'tier', 'bucket_start', 'grouping', ...$columns, ...($dimension !== null ? ['dimension'] : []), 'views', 'unique_visitors'],
            $query,
        );
    }

    private function firstViewedAt(?CarbonImmutable $from, CarbonImmutable $until): ?CarbonImmutable
    {
        $first = $this->view->newQuery()->toBase()
            ->when($from, fn (Builder $query, CarbonImmutable $from): Builder => $query->where('viewed_at', '>=', $from))
            ->where('viewed_at', '<', $until)
            ->min('viewed_at');

        return is_string($first) ? CarbonImmutable::parse($first) : null;
    }

    private function maxId(): ?int
    {
        $max = $this->view->newQuery()->toBase()->max('id');

        return $max === null ? null : (int) $max; // @phpstan-ignore cast.int (an integer column)
    }

    /** @throws RollupsNotInstalled */
    private function ensureInstalled(): void
    {
        if (! $this->rollup->getConnection()->getSchemaBuilder()->hasTable($this->rollup->getTable())) {
            throw RollupsNotInstalled::missingTable($this->rollup->getTable());
        }

        if (! $this->state->installed()) {
            throw RollupsNotInstalled::missingTable('view_retention_state');
        }
    }
}
