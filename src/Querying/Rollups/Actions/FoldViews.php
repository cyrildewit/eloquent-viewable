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
 * This action folds the views of closed buckets into the rollup table, every
 * tier from the views themselves, so unique visitors are exact per bucket at
 * every grain. A bucket is folded whole: its rows are deleted and inserted
 * again in one transaction, so a rerun or a refold is safe.
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
     * It folds every tier of every rollup, or only the tier and rollup named,
     * from where the last run stopped or again from `from`. A dry run counts
     * the buckets without folding them.
     *
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

        if (! $dryRun && ! $only instanceof Tier && $rollup === null) {
            $this->markLastId($maxId);
        }

        return $runs;
    }

    /**
     * @param  ?CarbonImmutable  $origin  moved back to the lowest bucket this tier folds
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

        $cursor = $this->cursor($tier, $snapshot, $from, $until);
        $lowest = $dirty === [] ? $cursor : $dirty[0]->min($cursor);
        $origin = $origin instanceof CarbonImmutable ? $origin->min($lowest) : $lowest;

        if (! $dryRun) {
            $this->markSince($definition, $tier, $snapshot->since($tier), $lowest);
        }

        while (($first = $this->firstViewedAt($cursor, $until)) instanceof CarbonImmutable) {
            $bucket = $tier->floor($first, $zone);
            $buckets += $this->foldBucket($definition, $tier, $bucket, $dryRun);
            $cursor = $tier->next($bucket, $zone);

            if (! $dryRun) {
                $this->markFolded($definition, $tier, $folded, $cursor);
            }
        }

        if (! $dryRun) {
            $this->markFolded($definition, $tier, $folded, $until);
        }

        return new ViewsRolledUp($definition->name, $tier, $folded, $until->max($folded ?? $until), $buckets);
    }

    /**
     * Folding starts again from `from` when it is given, from where the last
     * run stopped otherwise, and from the bucket of the first view on the
     * first run.
     */
    private function cursor(Tier $tier, Snapshot $snapshot, ?CarbonInterface $from, CarbonImmutable $until): CarbonImmutable
    {
        if ($from instanceof CarbonInterface) {
            return $this->refoldFrom($tier, $snapshot, $from);
        }

        $folded = $snapshot->folded($tier);

        if ($folded instanceof CarbonImmutable) {
            return $folded;
        }

        $first = $this->firstViewedAt(null, $until);

        if (! $first instanceof CarbonImmutable) {
            return $until;
        }

        return $tier->floor($first, $this->policy->timezone);
    }

    private function markSince(RollupDefinition $definition, Tier $tier, ?CarbonImmutable $since, CarbonImmutable $lowest): void
    {
        if ($since instanceof CarbonImmutable && $lowest >= $since) {
            return;
        }

        $this->state->putSince($definition->name, $tier, $lowest);
    }

    private function markFolded(RollupDefinition $definition, Tier $tier, ?CarbonImmutable $folded, CarbonImmutable $until): void
    {
        if ($folded instanceof CarbonImmutable && $until <= $folded) {
            return;
        }

        $this->state->putFolded($definition->name, $tier, $until);
    }

    /**
     * The last id moves only once every tier of every rollup has looked at
     * the views below it, or one left out would miss the late views of this
     * run.
     */
    private function markLastId(?int $maxId): void
    {
        if ($maxId === null) {
            return;
        }

        $this->state->putLastId($maxId);
    }

    /**
     * These are the buckets behind the watermark that views landed in since
     * the last run, as long as their views are all still there to fold again.
     *
     * @return list<CarbonImmutable>
     */
    private function dirtyBuckets(Tier $tier, Snapshot $snapshot, ?int $lastId, ?int $maxId): array
    {
        $folded = $snapshot->folded($tier);

        if (! $folded instanceof CarbonImmutable) {
            return [];
        }

        if ($lastId === null || $maxId === null) {
            return [];
        }

        if ($maxId <= $lastId) {
            return [];
        }

        $moments = $this->view
            ->newQuery()
            ->toBase()
            ->where('id', '>', $lastId)
            ->where('id', '<=', $maxId)
            ->where('viewed_at', '<', $folded)
            ->distinct()
            ->pluck('viewed_at');

        $floor = $this->refoldFloor($tier, $snapshot);
        $buckets = [];

        foreach ($moments as $moment) {
            $bucket = $tier->floor(CarbonImmutable::parse((string) $moment), $this->policy->timezone); // @phpstan-ignore cast.string (a timestamp column)

            if ($floor instanceof CarbonImmutable && $bucket < $floor) {
                continue;
            }

            $buckets[$bucket->format('Y-m-d H:i:s')] = $bucket;
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Folding again starts no earlier than the first bucket whose views are
     * all still there.
     */
    private function refoldFrom(Tier $tier, Snapshot $snapshot, CarbonInterface $from): CarbonImmutable
    {
        $zone = $this->policy->timezone;
        $from = $tier->floor($from, $zone);
        $floor = $this->refoldFloor($tier, $snapshot);

        if (! $floor instanceof CarbonImmutable) {
            return $from;
        }

        if ($from >= $floor) {
            return $from;
        }

        return $tier->ceil($floor, $zone);
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

        if (! $snapshot->pruned instanceof CarbonImmutable) {
            return $anonymised;
        }

        return $anonymised->max($snapshot->pruned);
    }

    /**
     * It returns one, the bucket, so a caller can count what it folded. The
     * totals of a custom rollup stay next to its rows per dimension value,
     * because unique visitors cannot be summed from those.
     */
    private function foldBucket(RollupDefinition $definition, Tier $tier, CarbonImmutable $start, bool $dryRun): int
    {
        if ($dryRun) {
            return 1;
        }

        $end = $tier->next($start, $this->policy->timezone);

        $this->rollup->getConnection()->transaction(function () use ($definition, $tier, $start, $end): void {
            $this->rollup
                ->newQuery()
                ->toBase()
                ->where('rollup', $definition->name)
                ->where('tier', $tier->value)
                ->where('bucket_start', $start)
                ->delete();

            foreach ($definition->groupings as $grouping) {
                $this->insert($definition, $tier, $grouping, $start, $end, null);

                if ($definition->dimension() !== null) {
                    $this->insert($definition, $tier, $grouping, $start, $end, $definition->dimension());
                }
            }
        });

        return 1;
    }

    /**
     * A dimension such as the JSON path `context->campaign` compiles to the
     * driver's own extraction.
     */
    private function insert(RollupDefinition $definition, Tier $tier, Grouping $grouping, CarbonImmutable $start, CarbonImmutable $end, ?string $dimension): void
    {
        $views = $this->view->newQuery();
        $definition->filter($views);

        $query = $views
            ->toBase()
            ->where('viewed_at', '>=', $start)
            ->where('viewed_at', '<', $end);

        $grammar = $query->getGrammar();
        $columns = $grouping->columns();
        $inserted = ['rollup', 'tier', 'bucket_start', 'grouping', ...$columns];

        $query
            ->selectRaw("?, ?, {$this->bucketPlaceholder()}, ?", [$definition->name, $tier->value, $start->format('Y-m-d H:i:s'), $grouping->stored($dimension !== null)]) // @phpstan-ignore argument.type (placeholders only, the values are bound)
            ->addSelect($columns)
            ->groupBy($columns);

        if ($dimension !== null) {
            $query->selectRaw($grammar->wrap($dimension))->groupByRaw($grammar->wrap($dimension)); // @phpstan-ignore argument.type, argument.type (a column or JSON path the application names, not user input)
            $inserted[] = 'dimension';
        }

        $query->selectRaw("count(*), count(distinct {$grammar->wrap('visitor')})"); // @phpstan-ignore argument.type (a wrapped identifier, not user input)

        $this->rollup
            ->newQuery()
            ->toBase()
            ->insertUsing([...$inserted, 'views', 'unique_visitors'], $query);
    }

    /**
     * Postgres types a bound value in a select list as text, which a
     * timestamp column refuses, so it gets a cast.
     */
    private function bucketPlaceholder(): string
    {
        if ($this->view->getConnection()->getDriverName() !== 'pgsql') {
            return '?';
        }

        return 'cast(? as timestamp)';
    }

    private function firstViewedAt(?CarbonImmutable $from, CarbonImmutable $until): ?CarbonImmutable
    {
        $first = $this->view
            ->newQuery()
            ->toBase()
            ->when($from, fn (Builder $query, CarbonImmutable $from): Builder => $query->where('viewed_at', '>=', $from))
            ->where('viewed_at', '<', $until)
            ->min('viewed_at');

        if (! is_string($first)) {
            return null;
        }

        return CarbonImmutable::parse($first);
    }

    private function maxId(): ?int
    {
        $max = $this->view->newQuery()->toBase()->max('id');

        if ($max === null) {
            return null;
        }

        return (int) $max; // @phpstan-ignore cast.int (an integer column)
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
