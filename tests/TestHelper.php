<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\View;

class TestHelper
{
    public static function createView(Viewable $viewable, $data = []): View
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
