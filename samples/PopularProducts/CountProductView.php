<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\PopularProducts;

use CyrildeWit\EloquentViewable\Events\ViewRecorded;

class CountProductView
{
    /**
     * Runs in the queue worker that stores the view, so it has no request,
     * session or user to read. It only needs the view itself.
     */
    public function handle(ViewRecorded $event): void
    {
        $view = $event->view;

        // Every viewable type fires the same event. Views recorded into a
        // collection are a different kind of view and are not counted either.
        if ($view->viewable_type !== new Product()->getMorphClass()
            || $view->collection !== null) {
            return;
        }

        // One atomic `UPDATE ... SET views_count = views_count + 1`, so views
        // stored by workers at the same time are all counted. It goes through
        // the base query builder because the Eloquent one would also set
        // `updated_at`, and a view is not an edit.
        Product::query()
            ->whereKey($view->viewable_id)
            ->toBase()
            ->increment('views_count');
    }
}
