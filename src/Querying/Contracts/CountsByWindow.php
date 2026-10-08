<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that counts every viewable of a type in a
 * window and in the windows it is compared with, for `rising()` and
 * `anomalies()`. It is kept apart from `ViewSource`, so a source of your own
 * keeps working without it.
 */
interface CountsByWindow
{
    /**
     * Each viewable is counted in the period of the query and in every
     * reference window, in their order. A viewable is left out unless its
     * count in the period, or its mean over the references, reaches the
     * minimum. The rows come in any order.
     *
     * @param  non-empty-list<Period>  $references
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     */
    public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array;
}
