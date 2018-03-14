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
    const PAST_SECONDS = 'PAST_SECONDS';
    const PAST_MINUTES = 'PAST_MINUTES';
    const PAST_DAYS = 'PAST_DAYS';
    const PAST_WEEKS = 'PAST_WEEKS';
    const PAST_MONTHS = 'PAST_MONTHS';
    const PAST_YEARS = 'PAST_YEARS';
}
