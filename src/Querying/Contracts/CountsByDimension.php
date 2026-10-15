<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that counts views per value of a column or
 * JSON path, such as `context->campaign`. It is kept apart from `ViewSource`,
 * so a source of your own keeps working without it.
 */
interface CountsByDimension
{
    /**
     * The counts are keyed by value, a view without one as an empty string.
     * Only values with views are present, in any order.
     *
     * @return array<string, int>
     */
    public function countByDimension(Viewable $viewable, ViewsQuery $query, string $dimension): array;
}
