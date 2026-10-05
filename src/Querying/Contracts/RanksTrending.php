<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * Implement this on a view source that ranks viewables by views weighed by
 * their age. The decay already holds every weight, so a source only has to
 * sort the views into its steps. It is kept apart from `ViewSource`, so a
 * source of your own keeps working without it.
 */
interface RanksTrending
{
    /**
     * Ranked by score, highest first, then by type and key. The count is the
     * number of views in the window, the score the sum of their weights. With
     * `unique`, a visitor is counted once per step, in both.
     *
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array;
}
