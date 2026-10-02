<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class WithViewsCount
{
    public function __construct(
        private ViewSource $source,
        private ViewsQuery $query,
        private string $as = 'views_count',
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
            ->selectSub($this->source->countSubquery($builder->getModel(), $this->query), $this->as)
            ->withCasts([$this->as => 'int']);
    }
}
