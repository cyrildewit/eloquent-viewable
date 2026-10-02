<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Scopes;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An existence check on the views relation rather than a count, so it reads
 * the views table directly instead of going through a ViewSource.
 */
final readonly class WhereViewed
{
    public function __construct(
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
        $constraint = function (Builder $views): void {
            /** @var Builder<View> $views */
            $views->matching($this->query);

            if ($this->visitor !== null) {
                $views->byVisitor($this->visitor);
            }
        };

        if ($this->not) {
            $builder->whereDoesntHave('views', $constraint);
        } else {
            $builder->whereHas('views', $constraint);
        }
    }
}
