<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidOperator;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class WhereViewsCount
{
    private const array Operators = ['=', '!=', '<>', '<', '<=', '>', '>='];

    public function __construct(
        private SubquerySource $source,
        private ViewsQuery $query,
        private string $operator,
        private int $count,
    ) {
        if (! in_array($operator, self::Operators, true)) {
            throw InvalidOperator::notAComparison($operator, implode(', ', self::Operators));
        }
    }

    /**
     * @template TModel of Model&Viewable
     *
     * @param  Builder<TModel>  $builder
     */
    public function __invoke(Builder $builder): void
    {
        $builder->where($this->source->countSubquery($builder->getModel(), $this->query), $this->operator, $this->count); // @phpstan-ignore argument.type (where() compiles a query builder column as a subquery, its docblock leaves that out)
    }
}
