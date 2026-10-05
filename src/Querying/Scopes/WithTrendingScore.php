<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class WithTrendingScore
{
    public function __construct(
        private TrendingSubquerySource $source,
        private ViewsQuery $query,
        private Decay $decay,
        private string $as = 'trending_score',
    ) {}

    /**
     * @template TModel of Model&Viewable
     *
     * @param  Builder<TModel>  $builder
     */
    public function __invoke(Builder $builder): void
    {
        if ($builder->getQuery()->columns === null) {
            $builder->select($builder->getModel()->qualifyColumn('*'));
        }

        $builder
            ->selectSub($this->source->trendingSubquery($builder->getModel(), $this->query, $this->decay), $this->as)
            ->withCasts([$this->as => 'float']);
    }
}
