<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

interface CountsViewsByInterval
{
    /**
     * Sparse counts keyed by the bucket start as `Y-m-d H:i:s` in the stored
     * wall clock. Buckets without views are absent.
     *
     * @return array<string, int>
     */
    public function handle(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array;
}
