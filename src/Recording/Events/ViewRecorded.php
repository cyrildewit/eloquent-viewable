<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Data\ViewRecord;

class ViewRecorded
{
    public function __construct(public ViewRecord $record) {}
}
