<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use DateTimeInterface;
use Exception;

class InvalidPeriod extends Exception
{
    public static function startDateTimeCannotBeAfterEndDateTime(DateTimeInterface $startDateTime, DateTimeInterface $endDateTime)
    {
        return new static("Start date `{$startDateTime->format('Y-m-d')}` cannot be after end date `{$endDateTime->format('Y-m-d')}`.");
    }
}
