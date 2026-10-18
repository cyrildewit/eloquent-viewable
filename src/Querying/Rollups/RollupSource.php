<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Dimensions\Normaliser;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\TracksChangedViewables;
use CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\StepCases;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Plan;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\PlannedRead;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\ReadPlanner;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Segment;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Querying\Sources\Rows;
use CyrildeWit\EloquentViewable\Querying\Sources\WindowTotals;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use JsonException;
use stdClass;

/**
 * This source reads recent views from the views table and older history from
 * the rollups, through every read and scope. A read the rollups cannot answer,
 * such as one narrowed to a viewer, reads the views table alone.
 *
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 */
final readonly class RollupSource implements CountsBy, CountsByDimension, CountsByWindow, CountsVisitFrequency, IdentifiesSource, RanksAlsoViewed, RanksRecommendations, RanksTrending, SubquerySource, TracksChangedViewables, TrendingSubquerySource, ViewSource
{
    private const int Chunk = 1_000;

    public function __construct(
        private DatabaseSource $raw,
        private View $view,
        private ViewRollup $rollup,
        private RollupPolicy $policy,
        private ReadPlanner $planner,
    ) {}

    /**
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        $grouping = Grouping::for($viewable, $query->collection !== null);
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->count($viewable, $query);
        }

        $raw = $this->rawCounts($read, $query, fn (ViewsQuery $segment): array => ['total' => $this->raw->count($viewable, $segment)]);

        return ($raw['total'] ?? 0) + (int) $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $read, $query)->sum($this->column($query));
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
        $read = $this->planner->plan($grouping, $query, $granularity);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countByInterval($viewable, $query, $granularity);
        }

        $counts = $this->rawCounts($read, $query, fn (ViewsQuery $segment): array => $this->raw->countByInterval($viewable, $segment, $granularity));

        $zone = $this->seriesZone($query);
        $start = $this->rollup->qualifyColumn('bucket_start');
        $tier = $this->rollup->qualifyColumn('tier');

        $rows = $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $read, $query)
            ->selectRaw("{$this->wrap($tier)} as tier, {$this->wrap($start)} as bucket_start, {$this->sum($query)} as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
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
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countByCollection($viewable, $query);
        }

        $counts = $this->rawCounts($read, $query, fn (ViewsQuery $segment): array => $this->raw->countByCollection($viewable, $segment));

        return $this->add($counts, $this->summed($this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $read, $query), 'collection', $query));
    }

    /**
     * Only the dimension of the rollup the query reads through is held in the
     * rollups. Any other dimension is counted from the views table.
     *
     * @return array<string, int>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension): array
    {
        if ($this->policy->for($query)?->dimension() !== $dimension) {
            return $this->raw->countByDimension($viewable, $query, $dimension);
        }

        $grouping = Grouping::for($viewable, $query->collection !== null);
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countByDimension($viewable, $query, $dimension);
        }

        $counts = $this->rawCounts($read, $query, fn (ViewsQuery $segment): array => $this->raw->countByDimension($viewable, $segment, $dimension));

        return $this->add($counts, $this->summed($this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $read, $query, perDimension: true), 'dimension', $query));
    }

    /**
     * The views table and the dimension's rollup each count their part of the
     * period, and the limit is applied to the sum. Unique visitors cannot be
     * summed across values, so their total is counted on its own.
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countBy(Viewable $viewable, ViewsQuery $query, DimensionDefinition $dimension, ?int $limit = null): DimensionCounts
    {
        $grouping = Grouping::for($viewable, $query->collection !== null);
        $read = $this->planner->plan($grouping, $query, counted: $dimension->name);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countBy($viewable, $query, $dimension, $limit);
        }

        $counts = DimensionCounts::from([]);

        foreach ($read->plan->raw() as $segment) {
            $counts = $counts->add($this->raw->countBy($viewable, $this->narrow($query, $segment), $dimension));
        }

        $column = $this->rollup->qualifyColumn('dimension');

        $rows = $this->rollups($viewable->getMorphClass(), ViewableKey::of($viewable), $grouping, $read, $query)
            ->selectRaw("{$this->wrap($column)} as dimension, {$this->sum($query)} as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($column)
            ->get();

        $counts = $counts->add($this->countsOf($rows));

        if ($query->unique) {
            $counts = DimensionCounts::from($counts->all(), $counts->none(), $counts->other(), $this->count($viewable, $query));
        }

        return $counts->limit($limit);
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
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countMany($viewable, $keys, $query);
        }

        $counts = $this->rawCounts($read, $query, fn (ViewsQuery $segment): array => $this->raw->countMany($viewable, $keys, $segment));

        foreach (array_chunk($keys, self::Chunk) as $chunk) {
            $rollups = $this->rollups($viewable->getMorphClass(), null, $grouping, $read, $query)->whereIn($this->rollup->qualifyColumn('viewable_id'), $chunk);

            $counts = $this->add($counts, $this->summed($rollups, 'viewable_id', $query));
        }

        return $counts;
    }

    /**
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        $grouping = Grouping::for(null, $query->collection !== null);
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->countSubquery($viewable, $query);
        }

        $parts = [];
        $bindings = [];

        foreach ($read->plan->raw() as $segment) {
            $raw = $this->raw->countSubquery($viewable, $this->narrow($query, $segment));
            $parts[] = "({$raw->toSql()})";
            $bindings = [...$bindings, ...$raw->getBindings()];
        }

        $rollups = $this->rollups($viewable->getMorphClass(), null, $grouping, $read, $query)
            ->whereColumn($this->rollup->qualifyColumn('viewable_id'), $viewable->getQualifiedKeyName())
            ->selectRaw("coalesce({$this->sum($query)}, 0)"); // @phpstan-ignore argument.type (a wrapped identifier, not user input)

        $parts[] = "({$rollups->toSql()})";

        return $this->view->getConnection()->query()
            ->selectRaw(implode(' + ', $parts), [...$bindings, ...$rollups->getBindings()]); // @phpstan-ignore argument.type (subqueries built here, their values are bound)
    }

    /**
     * Rollups keep no visitors, so the existence check reads the views table
     * alone.
     */
    public function viewsSubquery(Viewable $viewable, ViewsQuery $query, ?string $visitor = null): Builder
    {
        return $this->raw->viewsSubquery($viewable, $query, $visitor);
    }

    /**
     * Rollups keep no visitors, so the pairs are read from the views table
     * alone, over the views it still holds.
     *
     * @return list<array{type: string, id: int|string, count: int}>
     */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array
    {
        return $this->raw->alsoViewed($viewable, $among, $query, $limit, $minimum, $maxVisitors);
    }

    /**
     * Rollups keep no visitors, so the days are counted from the views table
     * alone, over the views it still holds.
     *
     * @return array<int, int>
     *
     * @throws InvalidInterval
     */
    public function visitFrequency(Viewable $viewable, ViewsQuery $query): array
    {
        return $this->raw->visitFrequency($viewable, $query);
    }

    /**
     * Rollups keep no visitors, so the pairs are read from the views table,
     * or the pairs table, alone.
     *
     * @return RecommendationPairs
     */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array
    {
        return $this->raw->recommendationPairs($request, $query);
    }

    /**
     * The views may be gone already, so the buckets of the built-in rollup
     * that start in the window count too.
     *
     * @return list<int|string>
     */
    public function keysViewedBetween(string $type, ?string $from, ?string $until): array
    {
        /** @var list<int|string> $buckets */
        $buckets = $this->rollup
            ->newQuery()
            ->toBase()
            ->where('rollup', RollupPolicy::BuiltIn)
            ->whereIn('grouping', [Grouping::Viewable->stored(), Grouping::ViewableCollection->stored()])
            ->where('viewable_type', $type)
            ->when($from, fn (Builder $query, string $from): Builder => $query->where('bucket_start', '>=', $from))
            ->when($until, fn (Builder $query, string $until): Builder => $query->where('bucket_start', '<', $until))
            ->distinct()
            ->pluck('viewable_id')
            ->all();

        return [...$this->raw->keysViewedBetween($type, $from, $until), ...$buckets];
    }

    /**
     * Nothing is pruned before it is folded, so a count stays the same.
     */
    public function countsChangeWhenPruned(): bool
    {
        return false;
    }

    /** @throws JsonException */
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
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return $this->raw->top($viewable, $query, $limit);
        }

        $type = $viewable?->getMorphClass();
        $branches = [...$this->rawBranches($read, $query, fn (ViewsQuery $segment): Builder => $this->raw->countedPerViewable($type, $segment)), $this->countedRollups($type, $grouping, $read, $query)];

        return Rows::ranked($this->ranked($branches, 'sum(aggregate) as aggregate', 'aggregate', $limit));
    }

    /**
     * Each window is planned on its own, so a recent window reads the views
     * table and an older one the rollups, and all of them are added up in one
     * statement.
     *
     * @param  non-empty-list<Period>  $references
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array
    {
        $type = $viewable?->getMorphClass();
        $windows = [$this->countedPerViewable($type, $query)];

        foreach ($references as $reference) {
            $windows[] = $this->countedPerViewable($type, $query->withPeriod($reference));
        }

        return WindowTotals::of($this->view->getConnection(), $windows, $minimum);
    }

    /**
     * Rollup rows are weighed by their bucket start, the views table by
     * `viewed_at`. Only tiers no coarser than the step answer, so a bucket
     * lies inside one step, and the scores match the views table's.
     *
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array
    {
        $query = $decay->narrow($query);
        $grouping = Grouping::for(null, $query->collection !== null);
        $read = $this->planner->trendingPlan($grouping, $query, $decay);

        if (! $read instanceof PlannedRead) {
            return $this->raw->trending($viewable, $query, $decay, $limit);
        }

        $type = $viewable?->getMorphClass();
        $branches = $this->rawBranches($read, $query, fn (ViewsQuery $segment): Builder => $this->raw->trendingRows($type, $segment, $decay));

        $rollupType = $this->rollup->qualifyColumn('viewable_type');
        $rollupId = $this->rollup->qualifyColumn('viewable_id');
        $column = $this->wrap($this->rollup->qualifyColumn($this->column($query)));
        [$weight, $bindings] = StepCases::weight($decay, $this->wrap($this->rollup->qualifyColumn('bucket_start')));

        $branches[] = $this->rollups($type, null, $grouping, $read, $query)
            ->selectRaw("{$this->wrap($rollupType)} as viewable_type, {$this->wrap($rollupId)} as viewable_id, sum({$column}) as aggregate, sum({$weight} * {$column}) as score", $bindings) // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)
            ->groupBy($rollupType, $rollupId);

        return Rows::scored($this->ranked($branches, 'sum(aggregate) as aggregate, sum(score) as score', 'score', $limit));
    }

    /**
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    public function trendingSubquery(Viewable $viewable, ViewsQuery $query, Decay $decay): Builder
    {
        $query = $decay->narrow($query);
        $grouping = Grouping::for(null, $query->collection !== null);
        $read = $this->planner->trendingPlan($grouping, $query, $decay);

        if (! $read instanceof PlannedRead) {
            return $this->raw->trendingSubquery($viewable, $query, $decay);
        }

        $parts = [];
        $bindings = [];

        foreach ($read->plan->raw() as $segment) {
            $raw = $this->raw->weightedSubquery($viewable, $this->narrow($query, $segment), $decay);
            $parts[] = "({$raw->toSql()})";
            $bindings = [...$bindings, ...$raw->getBindings()];
        }

        $column = $this->wrap($this->rollup->qualifyColumn($this->column($query)));
        [$weight, $weightBindings] = StepCases::weight($decay, $this->wrap($this->rollup->qualifyColumn('bucket_start')));

        $rollups = $this->rollups($viewable->getMorphClass(), null, $grouping, $read, $query)
            ->whereColumn($this->rollup->qualifyColumn('viewable_id'), $viewable->getQualifiedKeyName())
            ->selectRaw("coalesce(sum({$weight} * {$column}), 0)", $weightBindings); // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)

        $parts[] = "({$rollups->toSql()})";

        return Decay::scaled($this->view->getConnection()->query(), '('.implode(' + ', $parts).')', [...$bindings, ...$rollups->getBindings()]);
    }

    /**
     * It counts the views of each viewable in the period, as
     * `viewable_type`, `viewable_id` and `aggregate`, from the views table and
     * the rollups the period covers.
     *
     * @return non-empty-list<Builder>
     *
     * @throws InvalidPeriod
     * @throws ResolutionUnavailable
     */
    private function countedPerViewable(?string $type, ViewsQuery $query): array
    {
        $grouping = Grouping::for(null, $query->collection !== null);
        $read = $this->planner->plan($grouping, $query);

        if (! $read instanceof PlannedRead) {
            return [$this->raw->countedPerViewable($type, $query)];
        }

        return [...$this->rawBranches($read, $query, fn (ViewsQuery $segment): Builder => $this->raw->countedPerViewable($type, $segment)), $this->countedRollups($type, $grouping, $read, $query)];
    }

    /**
     * The rollup rows per viewable, as `viewable_type`, `viewable_id` and
     * `aggregate`, so they union with the branches of the views table.
     */
    private function countedRollups(?string $type, Grouping $grouping, PlannedRead $read, ViewsQuery $query): Builder
    {
        $rollupType = $this->rollup->qualifyColumn('viewable_type');
        $rollupId = $this->rollup->qualifyColumn('viewable_id');

        return $this->rollups($type, null, $grouping, $read, $query)
            ->selectRaw("{$this->wrap($rollupType)} as viewable_type, {$this->wrap($rollupId)} as viewable_id, {$this->sum($query)} as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($rollupType, $rollupId);
    }

    /**
     * The branches of the views table and the rollups added up per viewable
     * in one statement, the highest first.
     *
     * @param  non-empty-list<Builder>  $branches
     * @return Collection<int, stdClass>
     */
    private function ranked(array $branches, string $sums, string $orderBy, int $limit): Collection
    {
        return $this->view->getConnection()->query()
            ->fromSub(Rows::union($branches), 'ranked')
            ->selectRaw("viewable_type, viewable_id, {$sums}") // @phpstan-ignore argument.type (package-formatted literals, not user input)
            ->groupBy('viewable_type', 'viewable_id')
            ->orderByDesc($orderBy)
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->limit($limit)
            ->get();
    }

    /**
     * What the views table answers for each segment of the plan it covers.
     *
     * @param  Closure(ViewsQuery): Builder  $branch
     * @return list<Builder>
     *
     * @throws InvalidPeriod
     */
    private function rawBranches(PlannedRead $read, ViewsQuery $query, Closure $branch): array
    {
        return array_map(fn (Segment $segment): Builder => $branch($this->narrow($query, $segment)), $read->plan->raw());
    }

    /**
     * The counts the views table answers for the segments of the plan it
     * covers, added up.
     *
     * @template TKey of array-key
     *
     * @param  Closure(ViewsQuery): array<TKey, int>  $count
     * @return array<TKey, int>
     *
     * @throws InvalidPeriod
     */
    private function rawCounts(PlannedRead $read, ViewsQuery $query, Closure $count): array
    {
        $counts = [];

        foreach ($read->plan->raw() as $segment) {
            $counts = $this->add($counts, $count($this->narrow($query, $segment)));
        }

        return $counts;
    }

    /**
     * The rollup rows added up per value of a column.
     *
     * @return array<string, int>
     */
    private function summed(Builder $rollups, string $column, ViewsQuery $query): array
    {
        $qualified = $this->rollup->qualifyColumn($column);

        /** @var Collection<int|string, int|string> $rows */
        $rows = $rollups
            ->selectRaw("{$this->wrap($qualified)} as {$column}, {$this->sum($query)} as aggregate") // @phpstan-ignore argument.type (wrapped identifiers, not user input)
            ->groupBy($qualified)
            ->pluck('aggregate', $column);

        return Rows::ints($rows);
    }

    private function sum(ViewsQuery $query): string
    {
        return "sum({$this->wrap($this->rollup->qualifyColumn($this->column($query)))})";
    }

    /**
     * Where a bucket lands on the clock of a series. An hour bucket is
     * converted to the zone of the series, so a series in a zone a whole
     * number of hours away stays exact. A coarser bucket keeps its wall-clock
     * label, so January on the rollup clock stays January in the series and
     * does not become the last hours of December.
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
     * The rows of a dimension's rollup are only kept per value, and a query
     * narrowed by that dimension reads only the rows of its values.
     */
    private function rollups(?string $type, int|string|null $key, Grouping $grouping, PlannedRead $read, ViewsQuery $query, bool $perDimension = false): Builder
    {
        $column = fn (string $name): string => $this->rollup->qualifyColumn($name);
        $definition = $read->definition;

        $builder = $this->rollup
            ->newQuery()
            ->toBase()
            ->where($column('rollup'), $definition->name)
            ->where($column('grouping'), $grouping->stored($perDimension || $definition->dimensionOnly()))
            ->when($type !== null, fn (Builder $builder): Builder => $builder->where($column('viewable_type'), $type))
            ->when($key !== null, fn (Builder $builder): Builder => $builder->where($column('viewable_id'), $key));

        if ($query->collection !== null) {
            $builder->where($column('collection'), $query->collection);
        }

        if ($definition->dimensionOnly()) {
            foreach ($query->dimensions as $filter) {
                $builder->whereIn($column('dimension'), $filter->values);
            }
        }

        return $builder->where(function (Builder $builder) use ($read, $column): void {
            foreach ($read->plan->rollups() as $segment) {
                $builder->orWhere(fn (Builder $builder): Builder => $builder
                    ->where($column('tier'), $segment->tier?->value)
                    ->when($segment->start, fn (Builder $builder, CarbonImmutable $start): Builder => $builder->where($column('bucket_start'), '>=', $start))
                    ->when($segment->end, fn (Builder $builder, CarbonImmutable $end): Builder => $builder->where($column('bucket_start'), '<', $end)));
            }
        });
    }

    /**
     * The rows per value of a dimension's rollup, the views without one under
     * null and the views its cap folded away under `Normaliser::Other`.
     *
     * @param  Collection<int, stdClass>  $rows
     */
    private function countsOf(Collection $rows): DimensionCounts
    {
        $values = [];
        $none = 0;
        $other = 0;

        /** @var stdClass&object{dimension: ?string, aggregate: int|string} $row */
        foreach ($rows as $row) {
            if ($row->dimension === null) {
                $none += (int) $row->aggregate;

                continue;
            }

            if ($row->dimension === Normaliser::Other) {
                $other += (int) $row->aggregate;

                continue;
            }

            $values[$row->dimension] = (int) $row->aggregate;
        }

        return DimensionCounts::from($values, $none, $other);
    }

    private function column(ViewsQuery $query): string
    {
        if (! $query->unique) {
            return 'views';
        }

        return 'unique_visitors';
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
