<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use stdClass;

final readonly class DatabaseSource implements SubquerySource, ViewSource
{
    private const int CHUNK = 100;

    public function __construct(
        private View $view,
        private GrammarRegistry $grammars,
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

        // The default collection is stored as null, which pluck keys as the
        // empty string, as any PHP array does.
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
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        $counts = [];

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
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
        // The same SQL whereHas() builds on the views relation.
        $builder = $this->view->newQuery()
            ->whereColumn($viewable->getQualifiedKeyName(), $this->view->qualifyColumn('viewable_id'))
            ->where($this->view->qualifyColumn('viewable_type'), $viewable->getMorphClass())
            ->matching($query);

        if ($visitor !== null) {
            $builder->byVisitor($visitor);
        }

        return $builder->toBase();
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

        // Ordered by the alias, which every driver accepts, then by the group
        // columns so ties come back in the same order everywhere.
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

    private function countOne(Viewable $viewable, int|string $key, ViewsQuery $query): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase()
            ->where('viewable_type', $viewable->getMorphClass());
        $aggregate = $this->aggregate($query, $builder->getGrammar());

        // An integer is inlined, as Eloquent does when eager loading, which
        // spares two bindings per branch. Anything else is bound.
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
