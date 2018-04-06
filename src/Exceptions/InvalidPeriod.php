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

namespace CyrildeWit\EloquentViewable\Exceptions;

use DateTime;
use Exception;

/**
 * Class ProcessView.
 *
 * @see https://github.com/spatie/laravel-analytics/blob/master/src/Exceptions/InvalidPeriod.php
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class InvalidPeriod extends Exception
{
    /**
     * Execute the job.
     *
     * @param  \DateTime  $startDateTime
     * @param  \DateTime  $endDateTime
     * @return static
     */
    public static function startDateTimeCannotBeAfterEndDateTime(DateTime $startDateTime, DateTime $endDateTime)
    {
        return new static("Start date `{$startDateTime->format('Y-m-d')}` cannot be after end date `{$endDateTime->format('Y-m-d')}`.");
    }
}
