<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class OrderByViews
{
    /** @param  'asc'|'desc'  $direction */
    public function __construct(
        private ViewSource $source,
        private ViewsQuery $query,
        private string $direction = 'desc',
        private string $as = 'views_count',
    ) {}

    /**
     * @template TModel of Model&Viewable
     *
     * @param  Builder<TModel>  $builder
     */
    public function __invoke(Builder $builder): void
    {
        $builder
            ->tap(new WithViewsCount($this->source, $this->query, $this->as))
            ->orderBy($this->as, $this->direction);
    }
}
