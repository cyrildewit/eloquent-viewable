<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;

/**
 * A view source that can be queried in SQL. The Eloquent scopes embed these
 * queries in a query over the viewable's table, so they read from the
 * configured source only when it implements this, and every scope reads from
 * the same place. Both queries correlate on the viewable's qualified key.
 */
interface SubquerySource
{
    /**
     * Selects one integer, the count for the row of the outer query.
     */
    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder;

    /**
     * Selects the views of the row of the outer query, and only those of the
     * visitor when one is given, for an existence check.
     */
    public function viewsSubquery(Viewable $viewable, ViewsQuery $query, ?string $visitor = null): Builder;
}
