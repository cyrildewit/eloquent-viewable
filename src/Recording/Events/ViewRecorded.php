<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Data\ViewRecord;

/**
 * Dispatched once the store has accepted the record. With the database store
 * the row exists at that moment. A store that buffers writes lands the row
 * later, so a listener reads the data it needs from the record instead of
 * querying the views table for it.
 */
class ViewRecorded
{
    public function __construct(public ViewRecord $record) {}
}
