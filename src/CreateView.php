<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Contracts\ViewService as ViewServiceContract;

/**
 * Class CreateView.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class CreateView
{
    public function execute(array $data): ViewContract
    {
        $view = app(ViewContract::class);
        $view->viewable_id = $data['viewable_id'];
        $view->viewable_type = $data['viewable_type'];
        $view->visitor = $data['visitor'] ?? null;
        $view->tag = $data['tag'] ?? null;
        $view->viewed_at = $data['viewed_at'] ?? Carbon::now();
        $view->save();

        return $view;
    }
}
