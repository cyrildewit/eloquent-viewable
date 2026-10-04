<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Actions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Database\Query\Builder;

/**
 * Removes the rollup rows of a viewable whose views were destroyed. A model's
 * share of the unique visitors of its type cannot be taken out exactly, so
 * the type groupings keep it. A viewable without a key takes every row of
 * its type with it.
 */
final readonly class ForgetRollups
{
    public function __construct(
        private ViewRollup $rollup,
        private RollupPolicy $policy,
    ) {}

    public function handle(Viewable $viewable): void
    {
        if (! $this->policy->isEnabled() || ! $this->rollup->getConnection()->getSchemaBuilder()->hasTable($this->rollup->getTable())) {
            return;
        }

        $key = ViewableKey::of($viewable);

        $this->rollup->newQuery()->toBase()
            ->where('viewable_type', $viewable->getMorphClass())
            ->when($key !== null, fn (Builder $query): Builder => $query
                ->where('viewable_id', $key)
                ->whereIn('grouping', [
                    Grouping::Viewable->stored(),
                    Grouping::Viewable->stored(perDimension: true),
                    Grouping::ViewableCollection->stored(),
                    Grouping::ViewableCollection->stored(perDimension: true),
                ]))
            ->delete();
    }
}
