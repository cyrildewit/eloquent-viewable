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

namespace CyrildeWit\EloquentViewable\Enums;

/**
 * Abstract class PastType.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
abstract class PastType
{
    const PAST_SECONDS = 'subSeconds';
    const PAST_MINUTES = 'subMinutes';
    const PAST_DAYS = 'subDays';
    const PAST_WEEKS = 'subWeeks';
    const PAST_MONTHS = 'subMonths';
    const PAST_YEARS = 'subYears';
}
