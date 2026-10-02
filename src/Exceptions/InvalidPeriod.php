<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use DateTimeInterface;
use Exception;

final class InvalidPeriod extends Exception implements EloquentViewableException
{
    public static function startDateTimeCannotBeAfterEndDateTime(DateTimeInterface $startDateTime, DateTimeInterface $endDateTime): self
    {
        return new self("Start date `{$startDateTime->format('Y-m-d')}` cannot be after end date `{$endDateTime->format('Y-m-d')}`.");
    }

    public static function unparsable(string $period): self
    {
        return new self("`{$period}` is not a period. Use a shorthand such as `7d` or `3m`, or a range such as `2026-01-01..2026-02-01`, `2026-01-01..` or `..2026-02-01`.");
    }
}
