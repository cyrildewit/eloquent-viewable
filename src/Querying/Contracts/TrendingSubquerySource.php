<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;

/**
 * Implement this on a view source that can weigh views by age in SQL, for
 * the `withTrendingScore()` and `orderByTrending()` scopes. The query
 * correlates on the viewable's qualified key, like `SubquerySource`.
 */
interface TrendingSubquerySource
{
    /**
     * The query selects the score of the row of the outer query, 0 when it
     * has no views.
     */
    public function trendingSubquery(Viewable $viewable, ViewsQuery $query, Decay $decay): Builder;
}
