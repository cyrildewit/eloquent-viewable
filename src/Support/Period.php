<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Routing\UrlRoutable;

/**
 * A half-open range of time, `[start, end)`. The start is included and the end
 * is excluded. Either bound may be null, meaning unbounded on that side.
 *
 * Bounds are converted to the application timezone on construction, because
 * that is the wall clock `viewed_at` is stored in.
 *
 * A period has a string form, `7d`, `3m` or `2026-01-01..2026-02-01`, that
 * `parse()` reads and `getRouteKey()` writes, so it can travel in a URL and
 * bind to a route parameter.
 */
final readonly class Period implements UrlRoutable
{
    private const string RANGE_SEPARATOR = '..';

    private const string BOUND_PATTERN = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2})?$/';

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

    /**
     * A shorthand such as `7d`, `3w`, `6m` or `1y`, counted back from midnight
     * like `pastDays()`, or `90s`, `30min` or `12h`, counted back from now like
     * `subHours()`; or two ISO 8601 bounds around `..`, either of which may be
     * left out.
     *
     * @throws InvalidPeriod
     * @throws InvalidTimezone
     */
    public static function parse(string $period, DateTimeZone|string|null $timezone = null): self
    {
        $timezone = $timezone === null ? null : Timezone::from($timezone);

        if (preg_match('/^(\d+)([a-z]+)$/', $period, $matches) === 1) {
            $interval = PeriodInterval::fromShorthand($matches[2]) ?? throw InvalidPeriod::unparsable($period);

            return self::relative($interval->anchor(), $interval, (int) $matches[1], $timezone);
        }

        $bounds = explode(self::RANGE_SEPARATOR, $period);

        if (count($bounds) !== 2 || $bounds === ['', '']) {
            throw InvalidPeriod::unparsable($period);
        }

        foreach ($bounds as $bound) {
            if ($bound !== '' && preg_match(self::BOUND_PATTERN, $bound) !== 1) {
                throw InvalidPeriod::unparsable($period);
            }
        }

        try {
            return new self(
                $bounds[0] === '' ? null : Carbon::parse($bounds[0], $timezone),
                $bounds[1] === '' ? null : Carbon::parse($bounds[1], $timezone),
            );
        } catch (InvalidFormatException) {
            throw InvalidPeriod::unparsable($period);
        }
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
     * What `parse()` reads back. The timezone a relative period was built in
     * is not part of it; hand it to `parse()` again on the way back.
     */
    public function getRouteKey(): string
    {
        $shorthand = $this->relative?->shorthand();

        if ($shorthand !== null) {
            return $shorthand;
        }

        return $this->formatBound($this->startDateTime).self::RANGE_SEPARATOR.$this->formatBound($this->endDateTime);
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

    public function getRouteKeyName(): string
    {
        return 'period';
    }

    /**
     * Null for an unreadable value, which the router turns into a 404.
     */
    public function resolveRouteBinding(mixed $value, mixed $field = null): ?self // @phpstan-ignore method.childReturnType (the contract documents a Model, the router only needs a UrlRoutable)
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return self::parse($value);
        } catch (InvalidPeriod) {
            return null;
        }
    }

    public function resolveChildRouteBinding(mixed $childType, mixed $value, mixed $field): null
    {
        return null;
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

    private function formatBound(?CarbonInterface $bound): string
    {
        if (! $bound instanceof CarbonInterface) {
            return '';
        }

        return $bound->format($bound->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d\\TH:i:s');
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
