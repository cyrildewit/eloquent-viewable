<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that counts views per value of a dimension
 * from `dimensions.definitions`. It is kept apart from `ViewSource`, so a
 * source of your own keeps working without it.
 */
interface CountsBy
{
    /**
     * With a limit, only the values with the most views are kept, and the
     * views of the rest are counted in `other()`.
     */
    public function countBy(Viewable $viewable, ViewsQuery $query, DimensionDefinition $dimension, ?int $limit = null): DimensionCounts;
}
