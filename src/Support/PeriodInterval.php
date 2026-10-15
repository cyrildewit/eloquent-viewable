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

    public static function fromShorthand(string $unit): ?self
    {
        foreach (self::cases() as $interval) {
            if ($interval->shorthand() === $unit) {
                return $interval;
            }
        }

        return null;
    }

    public function shorthand(): string
    {
        return match ($this) {
            self::Seconds => 's',
            self::Minutes => 'min',
            self::Hours => 'h',
            self::Days => 'd',
            self::Weeks => 'w',
            self::Months => 'm',
            self::Years => 'y',
        };
    }

    /**
     * A calendar unit counts back from midnight, like `Period::pastDays()`;
     * a clock unit from now, like `Period::subHours()`.
     */
    public function anchor(): PeriodAnchor
    {
        return match ($this) {
            self::Seconds, self::Minutes, self::Hours => PeriodAnchor::Sub,
            self::Days, self::Weeks, self::Months, self::Years => PeriodAnchor::Past,
        };
    }

    public function subtract(CarbonInterface $dateTime, int $value): CarbonInterface
    {
        $dateTime = $dateTime->avoidMutation();

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
