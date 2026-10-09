<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Stores;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;

final readonly class NullStore implements ViewStore
{
    public function store(ViewRecord $record): void {}

    /** @param  iterable<ViewRecord>  $records */
    public function storeMany(iterable $records): void {}

    public function forget(Viewable $viewable): void {}
}
