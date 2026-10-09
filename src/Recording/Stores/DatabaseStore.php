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
     * One statement takes its columns from the first row, so every row is
     * given every column of the batch. A batch flushed from the buffer can mix
     * views recorded before a dimension was added with views recorded after.
     *
     * @param  iterable<ViewRecord>  $records
     */
    public function storeMany(iterable $records): void
    {
        $rows = [];
        $columns = [];

        foreach ($records as $record) {
            $row = $record->toArray();
            $rows[] = $row;
            $columns += array_fill_keys(array_keys($row), null);
        }

        $this->view->newQuery()->insert(array_map(static fn (array $row): array => $row + $columns, $rows));
    }

    public function forget(Viewable $viewable): void
    {
        $this->view->newQueryFor($viewable, new ViewsQuery)->delete();
    }
}
