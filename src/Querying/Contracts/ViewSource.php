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
     * Keyed by collection name, the default collection as an empty string.
     * Only collections with views are present, in any order.
     *
     * @return array<string, int>
     */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array;

    /**
     * The views of each key of the viewable's type, in one pass where the
     * source allows. Sparse: a key without views may be left out.
     *
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array;

    /**
     * Selects one integer, correlated on the viewable's qualified key.
     */
    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder;

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array;
}
