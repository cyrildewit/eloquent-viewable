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

namespace CyrildeWit\EloquentViewable\Support;

use CyrildeWit\EloquentViewable\CacheKey;
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

class Key
{
    /**
     * Create a unique key for the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @param  bool  $unique
     * @param  string  $collection
     * @return string
     */
    public static function createForEntity(ViewableContract $viewable, $period, bool $unique, string $collection = null): string
    {
        $cacheKey = CacheKey::fromViewable($viewable);

        return $cacheKey->make($period, $unique, $collection);
    }

    /**
     * Create a unique key for the viewable type.
     *
     * @param  string  $viewableType
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @param  bool  $unique
     * @param  string  $collection
     * @return string
     */
    public static function createForType($viewableType, $period, bool $unique, string $collection = null): string
    {
        $cacheKey = CacheKey::fromViewableType($viewableType);

        return $cacheKey->make($period, $unique, $collection);
    }
}
