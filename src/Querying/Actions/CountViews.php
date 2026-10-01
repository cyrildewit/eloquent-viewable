<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Actions;

use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViews as CountsViewsContract;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

final readonly class CountViews implements CountsViewsContract
{
    public function __construct(
        private ViewContract $view,
    ) {}

    public function handle(Viewable $viewable, ViewsQuery $query): int
    {
        $builder = $this->view->newQueryFor($viewable, $query);

        return $query->unique ? $builder->distinct()->count('visitor') : $builder->count();
    }
}
