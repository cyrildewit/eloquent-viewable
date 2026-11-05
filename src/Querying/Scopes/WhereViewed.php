<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * This scope checks for existence rather than counting, and reads from the
 * same source as the counting scopes so the two agree.
 */
final readonly class WhereViewed
{
    public function __construct(
        private SubquerySource $source,
        private ViewsQuery $query,
        private ?string $visitor = null,
        private bool $not = false,
    ) {}

    /**
     * @template TModel of Model&Viewable
     *
     * @param  Builder<TModel>  $builder
     */
    public function __invoke(Builder $builder): void
    {
        $views = $this->source->viewsSubquery($builder->getModel(), $this->query, $this->visitor);

        if ($this->not) {
            $builder->whereNotExists($views);

            return;
        }

        $builder->whereExists($views);
    }
}
