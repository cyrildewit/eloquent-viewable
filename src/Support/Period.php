<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use DateTimeInterface;
use DateTimeZone;

/**
 * A half-open range of time, `[start, end)`. The start is included and the end
 * is excluded. Either bound may be null, meaning unbounded on that side.
 *
 * Bounds are converted to the application timezone on construction, because
 * that is the wall clock `viewed_at` is stored in.
 */
final readonly class Period
{
    private ?CarbonInterface $startDateTime;

    private ?CarbonInterface $endDateTime;

    /** @throws InvalidPeriod */
    public function __construct(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null,
        /** @internal */
        private ?RelativePeriod $relative = null,
    ) {
        $this->startDateTime = Carbon::make($startDateTime)?->setTimezone(date_default_timezone_get());
        $this->endDateTime = Carbon::make($endDateTime)?->setTimezone(date_default_timezone_get());

        $this->guardChronologicalOrder();
    }

    /** @throws InvalidPeriod */
    public static function create(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null
    ): self {
        return new self($startDateTime, $endDateTime);
    }

    /** @throws InvalidPeriod */
    public static function since(DateTimeInterface|string|null $startDateTime = null): self
    {
        return new self($startDateTime);
    }

    /** @throws InvalidPeriod */
    public static function upto(DateTimeInterface|string|null $endDateTime = null): self
    {
        return new self(null, $endDateTime);
    }

    public static function pastDays(int $days, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Days, $days, $timezone);
    }

    public static function pastWeeks(int $weeks, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Weeks, $weeks, $timezone);
    }

    public static function pastMonths(int $months, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Months, $months, $timezone);
    }

    public static function pastYears(int $years, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Past, PeriodInterval::Years, $years, $timezone);
    }

    public static function subSeconds(int $seconds, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Seconds, $seconds, $timezone);
    }

    public static function subMinutes(int $minutes, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Minutes, $minutes, $timezone);
    }

    public static function subHours(int $hours, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Hours, $hours, $timezone);
    }

    public static function subDays(int $days, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Days, $days, $timezone);
    }

    public static function subWeeks(int $weeks, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Weeks, $weeks, $timezone);
    }

    public static function subMonths(int $months, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Months, $months, $timezone);
    }

    public static function subYears(int $years, DateTimeZone|string|null $timezone = null): self
    {
        return self::relative(PeriodAnchor::Sub, PeriodInterval::Years, $years, $timezone);
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
     * A stable string that identifies this period for caching, immune to
     * wall-clock drift for relative periods.
     *
     * @internal
     */
    public function cacheSignature(): string
    {
        return $this->relative?->signature()
            ?? "{$this->startDateTime?->timestamp}-{$this->endDateTime?->timestamp}";
    }

    /**
     * A relative period built without a zone of its own, re-anchored on the
     * clock of the timezone. Any other period is already a pair of instants.
     *
     * @internal
     */
    public function anchoredIn(Timezone $timezone): self
    {
        if (! $this->relative instanceof RelativePeriod || $this->relative->timezone instanceof Timezone) {
            return $this;
        }

        return self::relative($this->relative->anchor, $this->relative->interval, $this->relative->value, $timezone);
    }

    /**
     * @throws InvalidPeriod
     * @throws InvalidTimezone
     */
    private static function relative(PeriodAnchor $anchor, PeriodInterval $interval, int $value, DateTimeZone|string|null $timezone): self
    {
        $relative = new RelativePeriod($anchor, $interval, $value, $timezone === null ? null : Timezone::from($timezone));

        return new self($relative->startDateTime(), null, $relative);
    }

    /** @throws InvalidPeriod */
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
