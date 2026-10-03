<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
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

final readonly class DatabaseSource implements ViewSource
{
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

    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        $builder = $this->view->newQuery()->matching($query)->toBase();

        return $builder
            ->where($this->view->qualifyColumn('viewable_type'), $viewable->getMorphClass())
            ->whereColumn($this->view->qualifyColumn('viewable_id'), $viewable->getQualifiedKeyName())
            ->selectRaw($this->aggregate($query, $builder->getGrammar())); // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
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
