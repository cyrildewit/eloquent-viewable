<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonInterface;
use Carbon\Unit;

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

    public function unit(): Unit
    {
        return match ($this) {
            self::Seconds => Unit::Second,
            self::Minutes => Unit::Minute,
            self::Hours => Unit::Hour,
            self::Days => Unit::Day,
            self::Weeks => Unit::Week,
            self::Months => Unit::Month,
            self::Years => Unit::Year,
        };
    }

    public function subtract(CarbonInterface $dateTime, int $value): CarbonInterface
    {
        return $dateTime->avoidMutation()->sub($this->unit(), $value);
    }
}
