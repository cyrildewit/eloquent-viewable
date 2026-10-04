<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Support\Granularity;
use DateTimeZone;

/**
 * A tier is the width of a rollup bucket. Week is left out, because weeks do
 * not nest into months; a weekly series is built from day buckets.
 *
 * Moments go in and come out on the clock of `viewed_at`. The bucket edges
 * fall on the clock of the zone handed in.
 */
enum Tier: string
{
    case Hour = 'hour';

    case Day = 'day';

    case Month = 'month';

    case Year = 'year';

    /** @return list<self> */
    public static function coarseToFine(): array
    {
        return array_reverse(self::cases());
    }

    public function granularity(): Granularity
    {
        return match ($this) {
            self::Hour => Granularity::Hour,
            self::Day => Granularity::Day,
            self::Month => Granularity::Month,
            self::Year => Granularity::Year,
        };
    }

    public function isCoarserThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /**
     * A tier fits a granularity when every bucket of the tier lies inside one
     * bucket of the granularity, so a series of it can be summed from the
     * tier.
     */
    public function fits(Granularity $granularity): bool
    {
        return match ($granularity) {
            Granularity::Hour => $this === self::Hour,
            Granularity::Day, Granularity::Week => $this->rank() <= self::Day->rank(),
            Granularity::Month => $this->rank() <= self::Month->rank(),
            Granularity::Year => true,
        };
    }

    public function floor(CarbonInterface $moment, DateTimeZone $zone): CarbonImmutable
    {
        return $this->onClock($moment, $zone, fn (CarbonImmutable $local): CarbonInterface => $this->granularity()->floor($local));
    }

    public function next(CarbonInterface $bucket, DateTimeZone $zone): CarbonImmutable
    {
        return $this->onClock($bucket, $zone, fn (CarbonImmutable $local): CarbonInterface => $this->granularity()->add($local, 1));
    }

    public function ceil(CarbonInterface $moment, DateTimeZone $zone): CarbonImmutable
    {
        $floor = $this->floor($moment, $zone);

        if ($floor->equalTo($moment)) {
            return $floor;
        }

        return $this->next($floor, $zone);
    }

    /**
     * It counts the buckets in `[start, end)`, both on a bucket edge.
     */
    public function countBetween(CarbonInterface $start, CarbonInterface $end, DateTimeZone $zone): int
    {
        return $this->granularity()->countBetween(
            CarbonImmutable::instance($start)->setTimezone($zone),
            CarbonImmutable::instance($end)->setTimezone($zone),
        );
    }

    /** @param  callable(CarbonImmutable): CarbonInterface  $step */
    private function onClock(CarbonInterface $moment, DateTimeZone $zone, callable $step): CarbonImmutable
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($zone);

        return CarbonImmutable::instance($step($local))->setTimezone($moment->getTimezone());
    }

    private function rank(): int
    {
        return match ($this) {
            self::Hour => 0,
            self::Day => 1,
            self::Month => 2,
            self::Year => 3,
        };
    }
}
