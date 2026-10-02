<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Support\Granularity;
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

    /** @return array<string, int> */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();

        $expression = $this->grammars
            ->for($this->view->getConnection()->getDriverName())
            ->truncate($grammar->wrap('viewed_at'), $granularity);

        $aggregate = $this->aggregate($query, $grammar);

        /** @var Collection<int|string, int|string> $rows */
        $rows = $builder
            ->selectRaw("{$expression} as interval_start, {$aggregate} as aggregate") // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->groupByRaw($expression) // @phpstan-ignore argument.type (built from wrapped identifiers, not user input)
            ->pluck('aggregate', 'interval_start');

        $counts = [];

        foreach ($rows as $label => $count) {
            $counts[(string) $label] = (int) $count;
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
