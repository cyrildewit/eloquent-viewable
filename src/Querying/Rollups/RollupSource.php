<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Plan;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Planner;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Segment;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use DateTimeZone;
use JsonException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Reads recent views from the views table and older history from the rollup
 * tiers, through the same contract, so every count, series, ranking and scope
 * keeps working when the views behind them are gone. A query the rollups
 * cannot answer, such as one narrowed to a viewer or one that needs a
 * grouping that is not kept, reads the views table alone.
 */
final readonly class RollupSource implements CountsByDimension, IdentifiesSource, SubquerySource, ViewSource
{
    private const int CHUNK = 1_000;

    public function __construct(
        private DatabaseSource $raw,
        private View $view,
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private RollupState $state,
    ) {}

    /**
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        $grouping = Grouping::for($viewable, $query->collection !== null);
        $plan = $this->plan($grouping, $query);

        if (! $plan instanceof Plan) {
            return $this->raw->count($viewable, $query);
        }

        $total = 0;

        foreach ($plan->raw() as $segment) {
            $total += $this->raw->count($viewable, $this->narrow($query, $segment));
        }

        return $total + (int) $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $plan, $query)->sum($this->column($query));
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidInterval
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        $grouping = Grouping::for($viewable, $query->collection !== null);
        $plan = $this->plan($grouping, $query, $granularity);

        if (! $plan instanceof Plan) {
            return $this->raw->countByInterval($viewable, $query, $granularity);
        }

        $counts = [];

        foreach ($plan->raw() as $segment) {
            $counts = $this->add($counts, $this->raw->countByInterval($viewable, $this->narrow($query, $segment), $granularity));
        }

        $zone = $this->seriesZone($query);
        $start = $this->rollup->qualifyColumn('bucket_start');
        $tier = $this->rollup->qualifyColumn('tier');

        $rows = $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $plan, $query)
            ->selectRaw("{$this->wrap($tier)} as tier, {$this->wrap($start)} as bucket_start, sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}) as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($tier, $start)
            ->get();

        $buckets = [];

        /** @var stdClass&object{tier: string, bucket_start: string, aggregate: int|string} $row */
        foreach ($rows as $row) {
            $label = $granularity->floor($this->place(Tier::from($row->tier), CarbonImmutable::parse($row->bucket_start), $zone))->format('Y-m-d H:i:s');
            $buckets[$label] = ($buckets[$label] ?? 0) + (int) $row->aggregate;
        }

        return $this->add($counts, $buckets);
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array
    {
        $grouping = Grouping::for($viewable, true);
        $plan = $this->plan($grouping, $query);

        if (! $plan instanceof Plan) {
            return $this->raw->countByCollection($viewable, $query);
        }

        $counts = [];

        foreach ($plan->raw() as $segment) {
            $counts = $this->add($counts, $this->raw->countByCollection($viewable, $this->narrow($query, $segment)));
        }

        $collection = $this->rollup->qualifyColumn('collection');

        /** @var Collection<int|string, int|string> $rows */
        $rows = $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $plan, $query)
            ->selectRaw("{$this->wrap($collection)} as collection, sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}) as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($collection)
            ->pluck('aggregate', 'collection');

        $rollups = [];

        foreach ($rows as $name => $count) {
            $rollups[(string) $name] = (int) $count;
        }

        return $this->add($counts, $rollups);
    }

    /**
     * Per value of the dimension of the custom rollup the query reads
     * through. Any other dimension reads the views table alone.
     *
     * @return array<string, int>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension): array
    {
        $grouping = Grouping::for($viewable, $query->collection !== null);
        $plan = $this->policy->for($query)?->dimension() === $dimension ? $this->plan($grouping, $query) : null;

        if (! $plan instanceof Plan) {
            return $this->raw->countByDimension($viewable, $query, $dimension);
        }

        $counts = [];

        foreach ($plan->raw() as $segment) {
            $counts = self::add($counts, $this->raw->countByDimension($viewable, $this->narrow($query, $segment), $dimension));
        }

        $column = $this->rollup->qualifyColumn('dimension');

        /** @var Collection<int|string, int|string> $rows */
        $rows = $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $plan, $query, perDimension: true)
            ->selectRaw("{$this->wrap($column)} as dimension, sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}) as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($column)
            ->pluck('aggregate', 'dimension');

        $rollups = [];

        foreach ($rows as $value => $count) {
            $rollups[(string) $value] = (int) $count;
        }

        return self::add($counts, $rollups);
    }

    /**
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        $grouping = Grouping::for(null, $query->collection !== null);
        $plan = $this->plan($grouping, $query);

        if (! $plan instanceof Plan) {
            return $this->raw->countMany($viewable, $keys, $query);
        }

        $counts = [];

        foreach ($plan->raw() as $segment) {
            $counts = $this->add($counts, $this->raw->countMany($viewable, $keys, $this->narrow($query, $segment)));
        }

        $id = $this->rollup->qualifyColumn('viewable_id');

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            /** @var Collection<int|string, int|string> $rows */
            $rows = $this->rollups($viewable->getMorphClass(), null, $grouping, $plan, $query)
                ->whereIn($id, $chunk)
                ->selectRaw("{$this->wrap($id)} as viewable_id, sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}) as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
                ->groupBy($id)
                ->pluck('aggregate', 'viewable_id');

            $rollups = [];

            foreach ($rows as $key => $count) {
                $rollups[$key] = (int) $count;
            }

            $counts = $this->add($counts, $rollups);
        }

        return $counts;
    }

    /**
     * The sum of a correlated subquery per segment of the views table and
     * one over the rollups, so the scopes order and filter on all-time
     * counts without scanning every view.
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        $grouping = Grouping::for(null, $query->collection !== null);
        $plan = $this->plan($grouping, $query);

        if (! $plan instanceof Plan) {
            return $this->raw->countSubquery($viewable, $query);
        }

        $parts = [];
        $bindings = [];

        foreach ($plan->raw() as $segment) {
            $raw = $this->raw->countSubquery($viewable, $this->narrow($query, $segment));
            $parts[] = "({$raw->toSql()})";
            $bindings = [...$bindings, ...$raw->getBindings()];
        }

        $rollups = $this->rollups($viewable->getMorphClass(), null, $grouping, $plan, $query)
            ->whereColumn($this->rollup->qualifyColumn('viewable_id'), $viewable->getQualifiedKeyName())
            ->selectRaw("coalesce(sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}), 0)"); // @phpstan-ignore argument.type (a wrapped identifier, not user input)

        $parts[] = "({$rollups->toSql()})";

        return $this->view->getConnection()->query()
            ->selectRaw(implode(' + ', $parts), [...$bindings, ...$rollups->getBindings()]); // @phpstan-ignore argument.type (subqueries built here, their values are bound)
    }

    /**
     * An existence check reads the views table alone, like every read of a
     * viewer or visitor: the rollups keep neither.
     */
    public function viewsSubquery(Viewable $viewable, ViewsQuery $query, ?string $visitor = null): Builder
    {
        return $this->raw->viewsSubquery($viewable, $query, $visitor);
    }

    /**
     * The rollup table joins the identity of the views table, so two rollup
     * tables behind one cache store keep their entries apart.
     *
     * @throws JsonException
     */
    public function cacheIdentity(): string
    {
        return json_encode([$this->raw->cacheIdentity(), $this->policy->table], JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{type: string, id: int|string, count: int}>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
    {
        $grouping = Grouping::for(null, $query->collection !== null);
        $plan = $this->plan($grouping, $query);

        if (! $plan instanceof Plan) {
            return $this->raw->top($viewable, $query, $limit);
        }

        $type = $viewable?->getMorphClass();
        $branches = array_map(fn (Segment $segment): Builder => $this->rawRanking($type, $this->narrow($query, $segment)), $plan->raw());

        $rollupType = $this->rollup->qualifyColumn('viewable_type');
        $rollupId = $this->rollup->qualifyColumn('viewable_id');

        $branches[] = $this->rollups($type, null, $grouping, $plan, $query)
            ->selectRaw("{$this->wrap($rollupType)} as viewable_type, {$this->wrap($rollupId)} as viewable_id, sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))}) as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($rollupType, $rollupId);

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        // Ordered by the alias, then by the group columns so ties come back
        // in the same order everywhere, as the views table ranks them.
        $rows = $this->view->getConnection()->query()
            ->fromSub($union, 'ranked')
            ->selectRaw('viewable_type, viewable_id, sum(aggregate) as aggregate')
            ->groupBy('viewable_type', 'viewable_id')
            ->orderByDesc('aggregate')
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->limit($limit)
            ->get();

        $ranking = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, aggregate: int|string} $row */
        foreach ($rows as $row) {
            $ranking[] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'count' => (int) $row->aggregate];
        }

        return $ranking;
    }

    /**
     * Null when the views table answers alone.
     *
     * @throws ResolutionUnavailable
     */
    private function plan(Grouping $grouping, ViewsQuery $query, ?Granularity $granularity = null): ?Plan
    {
        $definition = $this->policy->for($query);

        if (! $definition instanceof RollupDefinition || $query->viewer instanceof Model || ! $definition->keeps($grouping)) {
            return null;
        }

        $tiers = $definition->tiers();

        if ($granularity instanceof Granularity) {
            $tiers = array_values(array_filter($tiers, static fn (Tier $tier): bool => $tier->fits($granularity)));
        }

        if ($tiers === []) {
            return null;
        }

        $zone = $this->seriesZone($query);
        $align = $granularity instanceof Granularity
            ? static fn (CarbonImmutable $moment): CarbonImmutable => CarbonImmutable::instance($granularity->floor($moment->setTimezone($zone)))->setTimezone($moment->getTimezone())
            : null;

        $plan = new Planner($this->policy->timezone)->plan(
            $this->state->snapshot($definition->name),
            $tiers,
            $query->period?->getStartDateTime(),
            $query->period?->getEndDateTime(),
            $query->unique,
            $align,
        );

        if ($plan->isRawOnly()) {
            return null;
        }

        if ($this->policy->strict) {
            $this->guard($plan, $query, $granularity);
        }

        return $plan;
    }

    /** @throws ResolutionUnavailable */
    private function guard(Plan $plan, ViewsQuery $query, ?Granularity $granularity): void
    {
        if (! $plan->isExact()) {
            throw ResolutionUnavailable::partialBucket();
        }

        if (! $granularity instanceof Granularity) {
            if ($query->unique && $plan->parts($this->policy->timezone) > 1) {
                throw ResolutionUnavailable::summedUniques();
            }

            return;
        }

        $zone = $this->seriesZone($query);

        if ($zone->getName() !== $this->policy->timezone->getName()) {
            foreach ($plan->rollups() as $segment) {
                if ($segment->tier !== Tier::Hour || ! $this->alignsByTheHour($segment, $zone)) {
                    throw ResolutionUnavailable::otherTimezone($zone->getName(), $this->policy->timezone->getName());
                }
            }
        }

        if (! $query->unique) {
            return;
        }

        foreach ($plan->rollups() as $segment) {
            if ($segment->tier?->granularity() !== $granularity) {
                throw ResolutionUnavailable::summedUniques();
            }
        }

        foreach ($plan->boundaries() as $boundary) {
            if (! $granularity->floor($boundary->setTimezone($zone))->equalTo($boundary)) {
                throw ResolutionUnavailable::summedUniques();
            }
        }
    }

    /**
     * Whether the hours of the rollups and of the series start together
     * across the segment, so every hour bucket lies inside one series bucket.
     */
    private function alignsByTheHour(Segment $segment, DateTimeZone $zone): bool
    {
        return array_all([$segment->start, $segment->end], fn (?CarbonImmutable $moment): bool => ! $moment instanceof CarbonImmutable || ($zone->getOffset($moment) - $this->policy->timezone->getOffset($moment)) % 3600 === 0);
    }

    /**
     * Where a bucket lands on the clock of a series. An hour is converted, so
     * a series in a zone a whole number of hours away stays exact. A coarser
     * bucket keeps its label: January on the rollup clock is January in the
     * series, rather than the last hours of December.
     */
    private function place(Tier $tier, CarbonImmutable $start, DateTimeZone $zone): CarbonImmutable
    {
        if ($tier === Tier::Hour) {
            return $start->setTimezone($zone);
        }

        return CarbonImmutable::parse($start->setTimezone($this->policy->timezone)->format('Y-m-d H:i:s'), $zone);
    }

    /** @throws InvalidPeriod */
    private function narrow(ViewsQuery $query, Segment $segment): ViewsQuery
    {
        return $query->withPeriod($segment->period());
    }

    /**
     * The rollup rows of the grouping inside the plan's rollup segments.
     */
    private function rollups(?string $type, int|string|null $key, Grouping $grouping, Plan $plan, ViewsQuery $query, bool $perDimension = false): Builder
    {
        $column = fn (string $name): string => $this->rollup->qualifyColumn($name);

        $builder = $this->rollup->newQuery()->toBase()
            ->where($column('rollup'), $this->policy->for($query)->name ?? RollupPolicy::BUILT_IN)
            ->where($column('grouping'), $grouping->stored($perDimension))
            ->when($type !== null, fn (Builder $builder): Builder => $builder->where($column('viewable_type'), $type))
            ->when($key !== null, fn (Builder $builder): Builder => $builder->where($column('viewable_id'), $key));

        if ($query->collection !== null) {
            $builder->where($column('collection'), $query->collection);
        }

        return $builder->where(function (Builder $builder) use ($plan, $column): void {
            foreach ($plan->rollups() as $segment) {
                $builder->orWhere(fn (Builder $builder): Builder => $builder
                    ->where($column('tier'), $segment->tier?->value)
                    ->when($segment->start, fn (Builder $builder, CarbonImmutable $start): Builder => $builder->where($column('bucket_start'), '>=', $start))
                    ->when($segment->end, fn (Builder $builder, CarbonImmutable $end): Builder => $builder->where($column('bucket_start'), '<', $end)));
            }
        });
    }

    /**
     * The views of one raw segment grouped per viewable, a branch of the
     * ranking union.
     */
    private function rawRanking(?string $type, ViewsQuery $query): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();
        $viewableType = $this->view->qualifyColumn('viewable_type');
        $viewableId = $this->view->qualifyColumn('viewable_id');
        $aggregate = $query->unique ? "count(distinct {$this->wrap($this->view->qualifyColumn('visitor'))})" : 'count(*)';

        return $builder
            ->when($type !== null, fn (Builder $builder): Builder => $builder->where($viewableType, $type))
            ->selectRaw("{$this->wrap($viewableType)} as viewable_type, {$this->wrap($viewableId)} as viewable_id, {$aggregate} as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($viewableType, $viewableId);
    }

    private function column(ViewsQuery $query): string
    {
        return $query->unique ? 'unique_visitors' : 'views';
    }

    private function seriesZone(ViewsQuery $query): Timezone
    {
        return $query->timezone ?? Timezone::application();
    }

    private function wrap(string $column): string
    {
        return $this->rollup->getConnection()->getQueryGrammar()->wrap($column);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $counts
     * @param  array<TKey, int>  $more
     * @return array<TKey, int>
     */
    private function add(array $counts, array $more): array
    {
        foreach ($more as $key => $count) {
            $counts[$key] = ($counts[$key] ?? 0) + $count;
        }

        return $counts;
    }
}
