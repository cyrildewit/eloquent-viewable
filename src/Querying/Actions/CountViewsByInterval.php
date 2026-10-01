<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Actions;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViewsByInterval as CountsViewsByIntervalContract;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Grammars\Grammar;

final readonly class CountViewsByInterval implements CountsViewsByIntervalContract
{
    public function __construct(
        private ViewContract $view,
        private GrammarRegistry $grammars,
    ) {}

    public function handle(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        $builder = $this->view->newQueryFor($viewable, $query)->toBase();
        $grammar = $builder->getGrammar();

        $expression = $this->grammars
            ->for($this->view->getConnection()->getDriverName())
            ->truncate($grammar->wrap('viewed_at'), $granularity);

        $aggregate = $this->aggregate($query, $grammar);

        $counts = [];

        foreach ($builder->selectRaw("{$expression} as interval_start, {$aggregate} as aggregate")->groupByRaw($expression)->pluck('aggregate', 'interval_start') as $label => $count) {
            $counts[(string) $label] = (int) $count;
        }

        return $counts;
    }

    /**
     * The SQL that counts the views inside one bucket. Identical on every
     * supported driver, so it does not belong to the bucket grammars.
     */
    private function aggregate(ViewsQuery $query, Grammar $grammar): string
    {
        return $query->unique
            ? "count(distinct {$grammar->wrap('visitor')})"
            : 'count(*)';
    }
}
