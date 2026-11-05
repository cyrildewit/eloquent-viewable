<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use CyrildeWit\EloquentViewable\Data\ViewRecord;

interface RecordsViews
{
    public function handle(ViewRecord $record): void;
}
