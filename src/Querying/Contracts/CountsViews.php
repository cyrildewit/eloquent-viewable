<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

interface CountsViews
{
    public function handle(Viewable $viewable, ViewsQuery $query): int;
}
