<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Actions;

use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews as RecordsViewsContract;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class RecordView implements RecordsViewsContract
{
    public function __construct(
        private ViewStore $store,
        private Dispatcher $events,
    ) {}

    public function handle(ViewRecord $record): void
    {
        $this->store->store($record);

        $this->events->dispatch(new ViewRecorded($record));
    }
}
