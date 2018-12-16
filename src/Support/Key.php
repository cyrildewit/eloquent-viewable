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

use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

class Key
{
    /**
     * Create a unique key for the viewable model.
     *
     * @param  \CyrildeWit\EloquentViewable\Contracts\Viewable  $viewable
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @param  bool  $unique
     * @return string
     */
    public static function createForEntity(ViewableContract $viewable, $period, bool $unique, string $collection = null): string
    {
        $cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');

        $viewableKey = $viewable->getKey();
        $viewableType = strtolower(str_replace('\\', '-', $viewable->getMorphClass()));

        $typeKey = $unique ? 'unique' : 'normal';
        $periodKey = static::createPeriodKey($period);

        $collection = $collection ? ".{$collection}" : '';

        return "{$cacheKey}{$collection}.{$viewableType}.{$viewableKey}.{$typeKey}.{$periodKey}";
    }

    /**
     * Create a unique key for the viewable type.
     *
     * @param  string  $viewableType
     * @param  \CyrildeWit\EloquentViewable\Support\Period  $period
     * @param  bool  $unique
     * @return string
     */
    public static function createForType($viewableType, $period, bool $unique): string
    {
        $cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');

        $viewableType = strtolower(str_replace('\\', '-', $viewableType));

        $typeKey = $unique ? 'unique' : 'normal';
        $periodKey = static::createPeriodKey($period);

        return "{$cacheKey}.{$viewableType}.{$typeKey}.{$periodKey}";
    }

    /**
     * Format a period class into a key.
     *
     * @param  \CyrildeWit\EloquentViewable\Support\Period
     * @return string
     */
    public static function createPeriodKey($period): string
    {
        if ($period->hasFixedDateTimes()) {
            return "{$period->getStartDateTimeString()}|{$period->getEndDateTimeString()}";
        }

        list($subType, $subValueType) = explode('_', strtolower($period->getSubType()));

        return "{$subType}{$period->getSubValue()}{$subValueType}|";
    }
}
