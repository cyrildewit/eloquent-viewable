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

use DateTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;

/**
 * Class Key.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class Key
{
    /**
     * Create a views count cache key.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $subject
     * @param  \CyrildeWit\subject\Support\Period  $period
     * @param  bool  $unique
     * @return string
     */
    public static function createForCache(Model $subject, $period, bool $unique): string
    {
        $cacheKey = config('eloquent-viewable.cache.key', 'cyrildewit.eloquent-viewable.cache');

        $subjectKey = $subject->getKey();
        $subjectType = strtolower(str_replace('\\', '-', $subject->getMorphClass()));

        $typeKey = $unique ? 'unique' : 'normal';
        $periodKey = $period->makeKey();

        return "{$cacheKey}.{$subjectType}.{$subjectKey}.{$typeKey}.{$periodKey}";
    }
}
