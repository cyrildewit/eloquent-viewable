<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\IdentifiesSource;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Querying\Pairs\PairTable;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\StepCases;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Support\AnonymisedVisitor;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use JsonException;
use stdClass;

/**
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 */
final readonly class DatabaseSource implements CountsByDimension, CountsByWindow, CountsVisitFrequency, IdentifiesSource, RanksAlsoViewed, RanksRecommendations, RanksTrending, SubquerySource, TrendingSubquerySource, ViewSource
{
    private const int Chunk = 100;

    public function __construct(
        private View $view,
        private GrammarRegistry $grammars,
        private CoVisitation $coVisitation,
        private PairTable $pairs,
    ) {}

    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        $builder = $this->view->newQueryFor($viewable, $query);

        return $query->unique ? $builder->distinct()->count('visitor') : $builder->count();
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidInterval
     */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();
        $bucketGrammar = $this->grammars->for($this->view->getConnection()->getDriverName());

        $column = $grammar->wrap('viewed_at');
        $conversion = $this->conversion($query);

        if ($conversion instanceof TimezoneConversion) {
            $column = $bucketGrammar->convertTimezone($column, $conversion);
        }

        $expression = $bucketGrammar->truncate($column, $granularity);
        $aggregate = $this->aggregate($query, $grammar);

        // Grouped by the alias, which every driver accepts and MariaDB needs:
        // its full-group-by check cannot match a case expression repeated verbatim.
        /** @var Collection<int|string, int|string> $rows */
        $rows = $builder
            ->selectRaw("{$expression} as interval_start, {$aggregate} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers and package-formatted literals, not user input)
            ->groupBy('interval_start')
            ->pluck('aggregate', 'interval_start');

        $counts = [];

        foreach ($rows as $label => $count) {
            $counts[(string) $label] = (int) $count;
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();

        $column = $grammar->wrap($this->view->qualifyColumn('collection'));
        $aggregate = $this->aggregate($query, $grammar);

        /** @var Collection<int|string, int|string> $rows */
        $rows = $builder
            ->selectRaw("{$column} as collection, {$aggregate} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy('collection')
            ->pluck('aggregate', 'collection');

        $counts = [];

        foreach ($rows as $name => $count) {
            $counts[(string) $name] = (int) $count;
        }

        return $counts;
    }

    /**
     * A dimension such as the JSON path `context->campaign` compiles to the
     * driver's own extraction, as in a where clause.
     *
     * @return array<string, int>
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();

        $column = $grammar->wrap($dimension);
        $aggregate = $this->aggregate($query, $grammar);

        /** @var Collection<int|string, int|string> $rows */
        $rows = $builder
            ->selectRaw("{$column} as dimension, {$aggregate} as aggregate") // @phpstan-ignore argument.type (a column or JSON path the application names, not user input)
            ->groupBy('dimension')
            ->pluck('aggregate', 'dimension');

        $counts = [];

        foreach ($rows as $value => $count) {
            $counts[(string) $value] = (int) $count;
        }

        return $counts;
    }

    /**
     * Counts the days each visitor viewed on in a derived table, then the
     * visitors per number of days. The day is truncated on the clock of the
     * query's timezone, like a bucket of `countByInterval()`.
     *
     * @return array<int, int>
     *
     * @throws InvalidInterval
     */
    public function visitFrequency(Viewable $viewable, ViewsQuery $query): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();
        $bucketGrammar = $this->grammars->for($this->view->getConnection()->getDriverName());

        $visitor = $this->view->qualifyColumn('visitor');
        $column = $grammar->wrap($this->view->qualifyColumn('viewed_at'));
        $conversion = $this->conversion($query);

        if ($conversion instanceof TimezoneConversion) {
            $column = $bucketGrammar->convertTimezone($column, $conversion);
        }

        $day = $bucketGrammar->truncate($column, Granularity::Day);

        $visitors = $builder
            ->whereNotNull($visitor)
            ->where($visitor, 'not like', AnonymisedVisitor::Prefix.'%')
            ->selectRaw("count(distinct {$day}) as days") // @phpstan-ignore argument.type (built from wrapped identifiers and package-formatted literals, not user input)
            ->groupBy($visitor);

        /** @var Collection<int|string, int|string> $rows */
        $rows = $this->view->getConnection()->query()
            ->fromSub($visitors, 'visitors')
            ->selectRaw('days, count(*) as aggregate')
            ->groupBy('days')
            ->pluck('aggregate', 'days');

        $counts = [];

        foreach ($rows as $days => $count) {
            $counts[(int) $days] = (int) $count;
        }

        return $counts;
    }

    /**
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        $counts = [];

        foreach (array_chunk($keys, self::Chunk) as $chunk) {
            $branches = array_map(fn (int|string $key): Builder => $this->countOne($viewable, $key, $query), $chunk);
            $statement = array_shift($branches);

            foreach ($branches as $branch) {
                $statement->unionAll($branch);
            }

            /** @var Collection<int|string, int|string> $rows */
            $rows = $statement->pluck('aggregate', 'viewable_id');

            foreach ($rows as $key => $count) {
                $counts[$key] = (int) $count;
            }
        }

        return $counts;
    }

    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();

        return $builder
            ->where($this->view->qualifyColumn('viewable_type'), $viewable->getMorphClass())
            ->whereColumn($this->view->qualifyColumn('viewable_id'), $viewable->getQualifiedKeyName())
            ->selectRaw($this->aggregate($query, $builder->getGrammar())); // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
    }

    public function viewsSubquery(Viewable $viewable, ViewsQuery $query, ?string $visitor = null): Builder
    {
        $builder = $this->view
            ->newQuery()
            ->whereColumn($viewable->getQualifiedKeyName(), $this->view->qualifyColumn('viewable_id'))
            ->where($this->view->qualifyColumn('viewable_type'), $viewable->getMorphClass())
            ->matching($query);

        if ($visitor !== null) {
            $builder->byVisitor($visitor);
        }

        return $builder->toBase();
    }

    /**
     * Two databases behind one cache store keep their entries apart.
     *
     * @throws JsonException
     */
    public function cacheIdentity(): string
    {
        $connection = $this->view->getConnection();

        return json_encode([$connection->getName(), $connection->getDatabaseName()], JSON_THROW_ON_ERROR);
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();

        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');

        if ($viewable instanceof Viewable) {
            $builder->where($type, $viewable->getMorphClass());
        }

        $rows = $builder
            ->selectRaw("{$grammar->wrap($type)}, {$grammar->wrap($id)}, {$this->aggregate($query, $grammar)} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($type, $id)
            ->orderByDesc('aggregate')
            ->orderBy($type)
            ->orderBy($id)
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
     * @param  non-empty-list<Period>  $references
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     */
    public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array
    {
        $type = $viewable?->getMorphClass();
        $windows = [[$this->countedPerViewable($type, $query)]];

        foreach ($references as $reference) {
            $windows[] = [$this->countedPerViewable($type, $query->withPeriod($reference))];
        }

        return WindowTotals::of($this->view->getConnection(), $windows, $minimum);
    }

    /**
     * It counts the views of each viewable in the period of the query, as
     * `viewable_type`, `viewable_id` and `aggregate`.
     *
     * @internal
     */
    public function countedPerViewable(?string $type, ViewsQuery $query): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();

        $viewableType = $this->view->qualifyColumn('viewable_type');
        $viewableId = $this->view->qualifyColumn('viewable_id');

        return $builder
            ->when($type !== null, fn (Builder $builder): Builder => $builder->where($viewableType, $type))
            ->selectRaw("{$grammar->wrap($viewableType)} as viewable_type, {$grammar->wrap($viewableId)} as viewable_id, {$this->aggregate($query, $grammar)} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($viewableType, $viewableId);
    }

    /**
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     *
     * @throws InvalidPeriod
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array
    {
        $rows = $this->view->getConnection()->query()
            ->fromSub($this->trendingRows($viewable?->getMorphClass(), $decay->narrow($query), $decay), 'ranked')
            ->orderByDesc('score')
            ->orderBy('viewable_type')
            ->orderBy('viewable_id')
            ->limit($limit)
            ->get();

        return self::scored($rows);
    }

    /**
     * The views of each viewable in the window, as `viewable_type`,
     * `viewable_id`, `aggregate` and the integer `score`. With `unique`, the
     * visitors are counted per step in a derived table first, because a
     * weighted distinct count has no meaning across steps.
     *
     * @internal
     */
    public function trendingRows(?string $type, ViewsQuery $query, Decay $decay): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();

        $viewableType = $this->view->qualifyColumn('viewable_type');
        $viewableId = $this->view->qualifyColumn('viewable_id');
        $viewedAt = $grammar->wrap($this->view->qualifyColumn('viewed_at'));

        if ($type !== null) {
            $builder->where($viewableType, $type);
        }

        $columns = "{$grammar->wrap($viewableType)} as viewable_type, {$grammar->wrap($viewableId)} as viewable_id";

        if (! $query->unique) {
            [$weight, $bindings] = StepCases::weight($decay, $viewedAt);

            return $builder
                ->selectRaw("{$columns}, count(*) as aggregate, sum({$weight}) as score", $bindings) // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)
                ->groupBy($viewableType, $viewableId);
        }

        [$index, $bindings] = StepCases::index($decay, $viewedAt);

        $stepped = $builder
            ->selectRaw("{$columns}, {$index} as step, {$this->aggregate($query, $grammar)} as visitors", $bindings) // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)
            ->groupBy($viewableType, $viewableId, 'step');

        return $this->view->getConnection()->query()
            ->fromSub($stepped, 'stepped')
            ->selectRaw('viewable_type, viewable_id, sum(visitors) as aggregate, sum('.StepCases::weightOfIndex($decay, 'step').' * visitors) as score') // @phpstan-ignore argument.type (integer literals, not user input)
            ->groupBy('viewable_type', 'viewable_id');
    }

    /**
     * @throws InvalidPeriod
     */
    public function trendingSubquery(Viewable $viewable, ViewsQuery $query, Decay $decay): Builder
    {
        $weighted = $this->weightedSubquery($viewable, $decay->narrow($query), $decay);

        return self::scaled($this->view->getConnection()->query(), "({$weighted->toSql()})", $weighted->getBindings());
    }

    /**
     * Selects an integer score scaled back. The divisor is a floating point
     * literal: SQLite and Postgres truncate integer division, and MySQL keeps
     * only four decimals when dividing by a decimal.
     *
     * @param  list<mixed>  $bindings
     *
     * @internal
     */
    public static function scaled(Builder $builder, string $score, array $bindings): Builder
    {
        return $builder->selectRaw("{$score} / 1e6", $bindings); // @phpstan-ignore argument.type (subqueries built by the sources, their values are bound)
    }

    /**
     * The integer score of the row of the outer query, 0 without views. With
     * `unique`, the derived table is compared with the outer key outside it,
     * because MariaDB refuses an outer reference inside a derived table.
     *
     * @internal
     */
    public function weightedSubquery(Viewable $viewable, ViewsQuery $query, Decay $decay): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();

        $viewableId = $this->view->qualifyColumn('viewable_id');
        $viewedAt = $grammar->wrap($this->view->qualifyColumn('viewed_at'));

        $builder->where($this->view->qualifyColumn('viewable_type'), $viewable->getMorphClass());

        if (! $query->unique) {
            [$weight, $bindings] = StepCases::weight($decay, $viewedAt);

            return $builder
                ->whereColumn($viewableId, $viewable->getQualifiedKeyName())
                ->selectRaw("coalesce(sum({$weight}), 0)", $bindings); // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)
        }

        [$index, $bindings] = StepCases::index($decay, $viewedAt);

        $stepped = $builder
            ->selectRaw("{$grammar->wrap($viewableId)} as viewable_id, {$index} as step, {$this->aggregate($query, $grammar)} as visitors", $bindings) // @phpstan-ignore argument.type (wrapped identifiers and integer literals, the step starts are bound)
            ->groupBy($viewableId, 'step');

        return $this->view->getConnection()->query()
            ->fromSub($stepped, 'stepped')
            ->whereColumn('stepped.viewable_id', $viewable->getQualifiedKeyName())
            ->selectRaw('coalesce(sum('.StepCases::weightOfIndex($decay, 'step').' * visitors), 0)'); // @phpstan-ignore argument.type (integer literals, not user input)
    }

    /**
     * The rows of a trending ranking, the integer score scaled back.
     *
     * @param  iterable<int, mixed>  $rows
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     *
     * @internal
     */
    public static function scored(iterable $rows): array
    {
        $ranking = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, aggregate: int|string, score: int|string|float} $row */
        foreach ($rows as $row) {
            $ranking[] = [
                'type' => $row->viewable_type,
                'id' => $row->viewable_id,
                'count' => (int) $row->aggregate,
                'score' => (float) $row->score / Decay::Scale,
            ];
        }

        return $ranking;
    }

    /**
     * Joins the views table to the visitors of the viewable, read as a
     * derived table so the cap on the visitors works on every driver: MySQL
     * refuses a limit inside `in (...)`. Views without a visitor never pair.
     * The pairs table answers instead when it is enabled and the query names
     * no period and no collection.
     *
     * @return list<array{type: string, id: int|string, count: int}>
     *
     * @throws InvalidConfiguration
     */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array
    {
        if ($this->pairs->serves($query)) {
            return $this->pairs->alsoViewed($viewable, $among, $limit, $minimum);
        }

        $visitor = $this->view->qualifyColumn('visitor');
        $type = $this->view->qualifyColumn('viewable_type');
        $id = $this->view->qualifyColumn('viewable_id');

        $visitors = $this->view->newQueryFor($viewable, $query)
            ->toBase()
            ->whereNotNull($visitor)
            ->select("{$visitor} as anchor_visitor")
            ->groupBy($visitor);

        if ($maxVisitors !== null) {
            $visitors->orderByRaw("max({$visitors->getGrammar()->wrap($this->view->qualifyColumn('viewed_at'))}) desc") // @phpstan-ignore argument.type (a wrapped identifier, not user input)
                ->orderBy($visitor)
                ->limit($maxVisitors);
        }

        $builder = $this->view->newQuery()->matching($query)->toBase();
        $grammar = $builder->getGrammar();
        $aggregate = "count(distinct {$grammar->wrap($visitor)})";

        $builder
            ->joinSub($visitors, 'anchor', 'anchor.anchor_visitor', '=', $visitor)
            ->where(static fn (Builder $pair): Builder => $pair
                ->where($type, '!=', $viewable->getMorphClass())
                ->orWhere($id, '!=', $viewable->getKey()));

        if ($among instanceof Viewable) {
            $builder->where($type, $among->getMorphClass());
        }

        $rows = $builder
            ->selectRaw("{$grammar->wrap($type)}, {$grammar->wrap($id)}, {$aggregate} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupBy($type, $id)
            ->havingRaw("{$aggregate} >= ?", [$minimum]) // @phpstan-ignore argument.type (built from wrapped identifiers, the minimum is bound)
            ->orderByDesc('aggregate')
            ->orderBy($type)
            ->orderBy($id)
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
     * The seeds always come from the views table. The pairs come from the
     * pairs table when it is enabled and the query names no period and no
     * collection.
     *
     * @return RecommendationPairs
     *
     * @throws InvalidConfiguration
     */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array
    {
        if (! $this->pairs->serves($query)) {
            return $this->coVisitation->recommendationPairs($request, $query);
        }

        $seeds = $this->coVisitation->seeds($request, $query);

        if ($seeds === []) {
            return ['seeds' => [], 'pairs' => [], 'audiences' => []];
        }

        return ['seeds' => $seeds, ...$this->pairs->recommendationPairs($request, $seeds)];
    }

    private function countOne(Viewable $viewable, int|string $key, ViewsQuery $query): Builder
    {
        $builder = $this->view
            ->newQuery()
            ->matching($query)
            ->toBase()
            ->where('viewable_type', $viewable->getMorphClass());
        $aggregate = $this->aggregate($query, $builder->getGrammar());

        if (is_int($key)) {
            return $builder
                ->whereIntegerInRaw('viewable_id', [$key])
                ->selectRaw("{$key} as viewable_id, {$aggregate} as aggregate"); // @phpstan-ignore argument.type (an integer and wrapped identifiers, not user input)
        }

        return $builder
            ->where('viewable_id', $key)
            ->selectRaw("? as viewable_id, {$aggregate} as aggregate", [$key]); // @phpstan-ignore argument.type (built from wrapped identifiers, the key is bound)
    }

    /**
     * Null when the query names no zone or the two clocks agree over the
     * period. The period bounds the conversion, so one is required.
     *
     * @throws InvalidInterval
     */
    private function conversion(ViewsQuery $query): ?TimezoneConversion
    {
        if (! $query->timezone instanceof Timezone) {
            return null;
        }

        $period = $query->period;
        $start = $period?->getStartDateTime();

        if (! $period instanceof Period || ! $start instanceof CarbonInterface) {
            throw InvalidInterval::periodWithoutStartDateTime();
        }

        $conversion = new TimezoneConversion(
            from: Timezone::application(),
            to: $query->timezone,
            start: $start,
            end: $period->getEndDateTime() ?? Carbon::now(),
        );

        return $conversion->isNoop() ? null : $conversion;
    }

    /**
     * The SQL that counts the views inside one bucket or one row. Identical
     * on every supported driver, so it does not belong to the bucket grammars.
     */
    private function aggregate(ViewsQuery $query, Grammar $grammar): string
    {
        return $query->unique
            ? "count(distinct {$grammar->wrap($this->view->qualifyColumn('visitor'))})"
            : 'count(*)';
    }
}
