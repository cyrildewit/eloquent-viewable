<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PopularProducts;

use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;

class CountProductView
{
    /**
     * Runs in the queue worker that stores the view, so it has no request,
     * session or user to read. It only needs the record itself.
     */
    public function handle(ViewRecorded $event): void
    {
        $record = $event->record;

        // Every viewable type fires the same event. Views recorded into a
        // collection are a different kind of view and are not counted either.
        if ($record->viewableType !== new Product()->getMorphClass()
            || $record->collection !== null) {
            return;
        }

        // One atomic `UPDATE ... SET views_count = views_count + 1`, so views
        // stored by workers at the same time are all counted. It goes through
        // the base query builder because the Eloquent one would also set
        // `updated_at`, and a view is not an edit.
        Product::query()
            ->whereKey($record->viewableId)
            ->toBase()
            ->increment('views_count');
    }
}
