<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use DateTimeInterface;

final readonly class Period
{
    private ?CarbonInterface $startDateTime;

    private ?CarbonInterface $endDateTime;

    /**
     * @throws InvalidPeriod
     */
    public function __construct(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null,
        /**
         * A stable signature for relative periods that keeps the cache key from
         * drifting as wall-clock time moves; `null` for absolute periods.
         *
         * @internal
         */
        private ?string $cacheSignature = null,
    ) {
        $this->startDateTime = Carbon::make($startDateTime);
        $this->endDateTime = Carbon::make($endDateTime);

        $this->guardChronologicalOrder();
    }

    /**
     * @throws InvalidPeriod
     */
    public static function create(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null
    ): self {
        return new self($startDateTime, $endDateTime);
    }

    /**
     * @throws InvalidPeriod
     */
    public static function since(DateTimeInterface|string|null $startDateTime = null): self
    {
        return new self($startDateTime);
    }

    /**
     * @throws InvalidPeriod
     */
    public static function upto(DateTimeInterface|string|null $endDateTime = null): self
    {
        return new self(null, $endDateTime);
    }

    public static function pastDays(int $days): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Days, $days);
    }

    public static function pastWeeks(int $weeks): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Weeks, $weeks);
    }

    public static function pastMonths(int $months): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Months, $months);
    }

    public static function pastYears(int $years): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Years, $years);
    }

    public static function subSeconds(int $seconds): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Seconds, $seconds);
    }

    public static function subMinutes(int $minutes): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Minutes, $minutes);
    }

    public static function subHours(int $hours): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Hours, $hours);
    }

    public static function subDays(int $days): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Days, $days);
    }

    public static function subWeeks(int $weeks): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Weeks, $weeks);
    }

    public static function subMonths(int $months): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Months, $months);
    }

    public static function subYears(int $years): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Years, $years);
    }

    public function getStartDateTime(): ?CarbonInterface
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): ?CarbonInterface
    {
        return $this->endDateTime;
    }

    /**
     * @internal
     */
    public function cacheSignature(): ?string
    {
        return $this->cacheSignature;
    }

    /**
     * @throws InvalidPeriod
     */
    private static function relative(PeriodAnchor $anchor, PeriodInterval $interval, int $value): self
    {
        $startDateTime = $interval->subtract($anchor->dateTime(), $value);

        return new self($startDateTime, null, "{$anchor->value}{$value}{$interval->value}");
    }

    /**
     * @throws InvalidPeriod
     */
    private function guardChronologicalOrder(): void
    {
        if (! $this->startDateTime instanceof CarbonInterface || ! $this->endDateTime instanceof CarbonInterface) {
            return;
        }

        if ($this->startDateTime > $this->endDateTime) {
            throw InvalidPeriod::startDateTimeCannotBeAfterEndDateTime(
                $this->startDateTime,
                $this->endDateTime,
            );
        }
    }
}
