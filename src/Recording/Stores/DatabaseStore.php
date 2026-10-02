<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

final readonly class DatabaseStore implements ViewStore
{
    public function __construct(private View $view) {}

    public function store(ViewRecord $record): void
    {
        $this->storeMany([$record]);
    }

    /**
     * One insert statement for the whole batch, none for an empty one. The
     * rows go through the query builder, so no Eloquent events are fired on
     * the view model.
     *
     * @param  iterable<ViewRecord>  $records
     */
    public function storeMany(iterable $records): void
    {
        $rows = [];

        foreach ($records as $record) {
            $rows[] = $record->toArray();
        }

        $this->view->newQuery()->insert($rows);
    }

    public function forget(Viewable $viewable): void
    {
        $this->view->newQueryFor($viewable, new ViewsQuery)->delete();
    }
}
