---
title: "How views are created"
description: "The RecordView action receives every ViewRecord that passed the guards, hands it to the store and dispatches ViewRecorded."
sidebar:
  order: 3
---

The `RecordView` action receives every `ViewRecord` that passed the guards, hands it to the store and dispatches
`ViewRecorded`. Bind your own to add attributes or skip the event. To only change where views are written,
[register a store](/eloquent-viewable/extending/stores/) instead.

```php
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;

final class RecordView implements RecordsViews
{
    public function handle(ViewRecord $record): void
    {
        // ...
    }
}

$this->app->bind(RecordsViews::class, \App\Actions\Views\RecordView::class);
```
