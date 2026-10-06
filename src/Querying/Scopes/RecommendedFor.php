<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * This scope keeps the models recommended to one recipient, selects their
 * score and orders by it, highest first and then by key. The scores are
 * read before the query runs, so further constraints and pagination apply
 * to every recommended model rather than to the first few.
 */
final readonly class RecommendedFor
{
    /** @param  array<int|string, float>  $scores */
    public function __construct(
        private array $scores,
        private string $as = 'recommendation_score',
    ) {}

    /**
     * The scores are written into the SQL as floating point literals: a bound
     * value would leave Postgres to type the case expression as text.
     *
     * @template TModel of Model&Viewable
     *
     * @param  Builder<TModel>  $builder
     */
    public function __invoke(Builder $builder): void
    {
        $model = $builder->getModel();
        $key = $model->getQualifiedKeyName();

        if ($this->scores === []) {
            $builder->whereKey([]);

            return;
        }

        if ($builder->getQuery()->columns === null) {
            $builder->select($model->qualifyColumn('*'));
        }

        $grammar = $builder->getQuery()->getGrammar();
        $cases = [];

        foreach ($this->scores as $score) {
            $literal = sprintf('%.15e', $score);

            $cases[] = "when {$grammar->wrap($key)} = ? then {$literal}";
        }

        $expression = implode(' ', $cases);

        $builder
            ->whereKey(array_keys($this->scores))
            ->selectRaw("case {$expression} else 0 end as {$grammar->wrap($this->as)}", array_keys($this->scores)) // @phpstan-ignore argument.type (wrapped identifiers and formatted floats, the keys are bound)
            ->withCasts([$this->as => 'float'])
            ->orderByDesc($this->as)
            ->orderBy($key);
    }
}
