<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Unit;
use Carbon\WeekDay;

/**
 * The width of a bucket when counting views over time.
 */
enum Granularity: string
{
    case Hour = 'hour';

    case Day = 'day';

    case Week = 'week';

    case Month = 'month';

    case Year = 'year';

    /**
     * Add a number of buckets to a date without mutating it.
     */
    public function add(CarbonInterface $dateTime, int $value): CarbonInterface
    {
        return $dateTime->avoidMutation()->add($this->unit(), $value);
    }

    /**
     * Snap a date to the start of its bucket. Weeks start on Monday, matching
     * the SQL, regardless of the application locale.
     */
    public function floor(CarbonInterface $dateTime): CarbonInterface
    {
        $dateTime = $dateTime->avoidMutation();

        return match ($this) {
            self::Week => $dateTime->startOfWeek(WeekDay::Monday),
            default => $dateTime->startOf($this->unit()),
        };
    }

    /**
     * The number of buckets a walk from the floored start up to the exclusive
     * end produces. Computed on the wall clock, so a DST transition inside the
     * range neither adds nor removes a bucket.
     */
    public function countBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $from = self::wallClock($this->floor($start));
        $to = self::wallClock($end);

        return (int) ceil($from->diffInUnit($this->unit(), $to));
    }

    private function unit(): Unit
    {
        return match ($this) {
            self::Hour => Unit::Hour,
            self::Day => Unit::Day,
            self::Week => Unit::Week,
            self::Month => Unit::Month,
            self::Year => Unit::Year,
        };
    }

    private static function wallClock(CarbonInterface $dateTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $dateTime->format('Y-m-d H:i:s'), 'UTC');
    }
}
