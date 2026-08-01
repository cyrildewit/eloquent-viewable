<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;

enum PeriodInterval: string
{
    case Seconds = 'seconds';

    case Minutes = 'minutes';

    case Hours = 'hours';

    case Days = 'days';

    case Weeks = 'weeks';

    case Months = 'months';

    case Years = 'years';

    public function subtract(CarbonInterface $dateTime, int $value): CarbonInterface
    {
        return match ($this) {
            self::Seconds => $dateTime->subSeconds($value),
            self::Minutes => $dateTime->subMinutes($value),
            self::Hours => $dateTime->subHours($value),
            self::Days => $dateTime->subDays($value),
            self::Weeks => $dateTime->subWeeks($value),
            self::Months => $dateTime->subMonths($value),
            self::Years => $dateTime->subYears($value),
        };
    }
}
