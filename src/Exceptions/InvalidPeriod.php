<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use DateTime;
use Exception;

/**
 * @see https://github.com/spatie/laravel-analytics/blob/master/src/Exceptions/InvalidPeriod.php
 */
class InvalidPeriod extends Exception
{
    /**
     * Start date time cannot be after end eate time.
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
