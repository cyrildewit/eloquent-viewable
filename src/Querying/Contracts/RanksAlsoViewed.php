<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that ranks what the visitors of one
 * viewable also viewed. It is kept apart from `ViewSource`, so a source of
 * your own keeps working without it.
 */
interface RanksAlsoViewed
{
    /**
     * Ranked by the number of distinct visitors who viewed both, most first,
     * then by type and key. The viewable itself is never in the ranking. A
     * viewable seen by fewer than `$minimum` of the visitors is left out, and
     * only the `$maxVisitors` most recent visitors of the viewable are read
     * when it is not null. The query is matched on both sides of the pair.
     *
     * @return list<array{type: string, id: int|string, count: int}>
     */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array;
}
