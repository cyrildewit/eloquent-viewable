<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;

/**
 * A viewable without a key stands for every viewable of its type.
 */
interface ViewSource
{
    public function count(Viewable $viewable, ViewsQuery $query): int;

    /**
     * Sparse, keyed by the bucket start as `Y-m-d H:i:s`.
     *
     * @return array<string, int>
     */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array;

    /**
     * Selects one integer, correlated on the viewable's qualified key.
     */
    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder;
}
