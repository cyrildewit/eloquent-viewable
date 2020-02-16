<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;

class TestHelper
{
    /**
     * Helper function to create a view.
     *
     * @param  array  $data
     * @return \CyrildeWit\EloquentViewable\View
     */
    public static function createView($model, $data = [])
    {
        return View::create([
            'viewable_id' => $model->getKey(),
            'viewable_type' => $model->getMorphClass(),
            'visitor' => $data['visitor'] ?? 'unique_hash',
            'collection' => $data['collection'] ?? null,
            'viewed_at' => $data['viewed_at'] ?? Carbon::now(),
        ]);
    }
}
