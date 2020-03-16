<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class TestHelper
{
    /**
     * Helper function to create a view.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  array  $data
     * @return \CyrildeWit\EloquentViewable\View
     */
    public static function createView(Viewable $viewable, $data = [])
    {
        return View::create([
            'viewable_id' => $viewable->getKey(),
            'viewable_type' => $viewable->getMorphClass(),
            'visitor' => $data['visitor'] ?? 'unique_hash',
            'collection' => $data['collection'] ?? null,
            'viewed_at' => $data['viewed_at'] ?? Carbon::now(),
        ]);
    }
}
