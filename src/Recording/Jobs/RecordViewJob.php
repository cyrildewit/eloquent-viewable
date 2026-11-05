<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Jobs;

use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RecordViewJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public ViewRecord $record) {}

    public function handle(RecordsViews $action): void
    {
        $action->handle($this->record);
    }
}
