<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;

final class ArrayStore implements ViewStore
{
    /** @var list<ViewRecord> */
    private array $records = [];

    public function store(ViewRecord $record): void
    {
        $this->storeMany([$record]);
    }

    /** @param  iterable<ViewRecord>  $records */
    public function storeMany(iterable $records): void
    {
        foreach ($records as $record) {
            $this->records[] = $record;
        }
    }

    public function forget(Viewable $viewable): void
    {
        $this->records = array_values(array_filter(
            $this->records,
            fn (ViewRecord $record): bool => ! $record->belongsTo($viewable),
        ));
    }

    /** @return list<ViewRecord> */
    public function records(): array
    {
        return $this->records;
    }
}
