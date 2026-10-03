<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

/**
 * Reads never go through a store. A store that writes elsewhere is a buffer
 * in front of the views table and has to land its records there, which is
 * what storeMany() is for. Writes do not fire Eloquent events on the view
 * model.
 */
interface ViewStore
{
    public function store(ViewRecord $record): void;

    /**
     * Store a batch of records in as few writes as the store allows.
     *
     * @param  iterable<ViewRecord>  $records
     */
    public function storeMany(iterable $records): void;

    /**
     * Every view of the viewable, in every collection, buffered or stored.
     */
    public function forget(Viewable $viewable): void;
}
