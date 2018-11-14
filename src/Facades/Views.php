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

namespace CyrildeWit\EloquentViewable\Facades;

use Illuminate\Support\Facades\Facade;
use CyrildeWit\EloquentViewable\Contracts\Views as ViewsContract;

/**
 * @see \CyrildeWit\EloquentViewable\Views
 */
class Views extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ViewsContract::class;
    }
}
