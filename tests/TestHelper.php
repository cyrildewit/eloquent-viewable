<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Models\View;

/**
 * TestHelper class.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class TestHelper
{
    /**
     * Helper function to create a view.
     *
     * @param  \Carbon\Carbon  $viewedAt
     * @return \CyrildeWit\EloquentViewable\Models\View
     */
    public static function createNewView($model, $viewedAt)
    {
        return View::create([
            'viewable_id' => $model->getKey(),
            'viewable_type' => get_class($model),
            'cookie_value' => '130.57.143.127',
            'viewed_at' => $viewedAt,
        ]);
    }
}
