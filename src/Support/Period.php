<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use DateTimeInterface;
use Illuminate\Support\Str;

class Period
{
    /**
     * Available past types.
     */
    const PAST_DAYS = 'PAST_DAYS';
    const PAST_WEEKS = 'PAST_WEEKS';
    const PAST_MONTHS = 'PAST_MONTHS';
    const PAST_YEARS = 'PAST_YEARS';

    /**
     * Available sub types.
     */
    const SUB_SECONDS = 'SUB_SECONDS';
    const SUB_MINUTES = 'SUB_MINUTES';
    const SUB_HOURS = 'SUB_HOURS';
    const SUB_DAYS = 'SUB_DAYS';
    const SUB_WEEKS = 'SUB_WEEKS';
    const SUB_MONTHS = 'SUB_MONTHS';
    const SUB_YEARS = 'SUB_YEARS';

    protected ?CarbonInterface $startDateTime;

    protected ?CarbonInterface $endDateTime;

    protected bool $fixedDateTimes = true;

    protected string $subType;

    protected int $subValue;

    /**
     * @param  \DateTimeInterface|string|null  $startDateTime
     * @param  \DateTimeInterface|string|null  $endDateTime
     */
    public function __construct($startDateTime = null, $endDateTime = null)
    {
        $startDateTime = $this->resolveDateTime($startDateTime);
        $endDateTime = $this->resolveDateTime($endDateTime);

        if ($startDateTime instanceof DateTimeInterface && $endDateTime instanceof DateTimeInterface) {
            if ($startDateTime > $endDateTime) {
                throw InvalidPeriod::startDateTimeCannotBeAfterEndDateTime($startDateTime, $endDateTime);
            }
        }

        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
    }

    /**
     * Create a new Period instance.
     *
     * @param  \DateTimeInterface|string|null  $startDateTime
     * @param  \DateTimeInterface|string|null  $endDateTime
     */
    public static function create($startDateTime = null, $endDateTime = null): self
    {
        return new static($startDateTime, $endDateTime);
    }

    /**
     * Create a new Period instance with only a start date time.
     *
     * @param  \DateTimeInterface|string|null  $startDateTime
     */
    public static function since($startDateTime = null): self
    {
        return new static($startDateTime);
    }

    /**
     * Create a new Period instance with only a end date time.
     *
     * @param  \DateTimeInterface|string|null  $endDateTime
     */
    public static function upto($endDateTime = null): self
    {
        return new static(null, $endDateTime);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given days.
     *
     * Start Date Time: Carbon::today()->subDays(2);
     */
    public static function pastDays(int $days): self
    {
        return self::subToday(self::PAST_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given weeks.
     *
     * Start Date Time: Carbon::today()->subWeeks(2);
     */
    public static function pastWeeks(int $weeks): self
    {
        return self::subToday(self::PAST_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given months.
     *
     * Start Date Time: Carbon::today()->subMonths(2);
     */
    public static function pastMonths(int $months): self
    {
        return self::subToday(self::PAST_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given years.
     *
     * Start Date Time: Carbon::today()->subYears(2);
     */
    public static function pastYears(int $years): self
    {
        return self::subToday(self::PAST_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given seconds.
     *
     * Start Date Time: Carbon::now()->subSeconds(2);
     */
    public static function subSeconds(int $seconds): self
    {
        return self::subNow(self::SUB_SECONDS, $seconds);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given minutes.
     *
     * Start Date Time: Carbon::now()->subMinutes(2);
     */
    public static function subMinutes(int $minutes): self
    {
        return self::subNow(self::SUB_MINUTES, $minutes);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given hours.
     *
     * Start Date Time: Carbon::now()->subHours(2);
     */
    public static function subHours(int $hours): self
    {
        return self::subNow(self::SUB_HOURS, $hours);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given days.
     *
     * Start Date Time: Carbon::now()->subDays(2);
     */
    public static function subDays(int $days): self
    {
        return self::subNow(self::SUB_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given weeks.
     *
     * Start Date Time: Carbon::now()->subWeeks(2);
     */
    public static function subWeeks(int $weeks): self
    {
        return self::subNow(self::SUB_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given months.
     *
     * Start Date Time: Carbon::now()->subMonths(2);
     */
    public static function subMonths(int $months): self
    {
        return self::subNow(self::SUB_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given years.
     *
     * Start Date Time: Carbon::now()->suYears(2);
     */
    public static function subYears(int $years): self
    {
        return self::subNow(self::SUB_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given subType.
     *
     * Start Date Time: Carbon::today()->sub<subType>(<subValue>);
     */
    public static function subToday(string $subType, int $subValue): self
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(Str::after($subType, 'PAST_')));
        $today = Carbon::today();

        return self::sub($today, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given subType.
     *
     * Start Date Time: Carbon::now()->sub<subType>(<subValue>);
     */
    public static function subNow(string $subType, int $subValue): self
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(Str::after($subType, 'SUB_')));
        $now = Carbon::now();

        return self::sub($now, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of startDateTime minus the given subType.
     *
     * Start Date Time: <startDateTime>->sub<subType>(<subValue>);
     */
    public static function sub(DateTimeInterface $startDateTime, string $subTypeMethod, string $subType, int $subValue): self
    {
        $startDateTime = $startDateTime->$subTypeMethod($subValue);

        $period = new static($startDateTime);

        return $period->setFixedDateTimes(false)
            ->setSubType($subType)
            ->setSubValue($subValue);
    }

    public function getStartDateTime(): ?DateTimeInterface
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): ?DateTimeInterface
    {
        return $this->endDateTime;
    }

    public function hasFixedDateTimes(): bool
    {
        return $this->fixedDateTimes;
    }

    public function getStartDateTimeString(): string
    {
        return $this->startDateTime !== null ? $this->startDateTime->toDateTimeString() : '';
    }

    public function getEndDateTimeString(): string
    {
        return $this->endDateTime !== null ? $this->endDateTime->toDateTimeString() : '';
    }

    public function getStartDateTimestamp(): ?int
    {
        return $this->startDateTime !== null ? $this->startDateTime->getTimestamp() : null;
    }

    public function getEndDateTimestamp(): ?int
    {
        return $this->endDateTime !== null ? $this->endDateTime->getTimestamp() : null;
    }

    public function getSubType(): string
    {
        return $this->subType;
    }

    public function getSubValue(): int
    {
        return $this->subValue;
    }

    public function setStartDateTime(DateTimeInterface $startDateTime): self
    {
        $this->startDateTime = Carbon::instance($startDateTime);

        return $this;
    }

    public function setEndDateTime(DateTimeInterface $endDateTime): self
    {
        $this->endDateTime = Carbon::instance($endDateTime);

        return $this;
    }

    public function setFixedDateTimes(bool $status): self
    {
        $this->fixedDateTimes = $status;

        return $this;
    }

    public function setSubType(string $subType): self
    {
        $this->subType = $subType;

        return $this;
    }

    public function setSubValue(int $subValue): self
    {
        $this->subValue = $subValue;

        return $this;
    }

    protected function resolveDateTime($dateTime)
    {
        if ($dateTime instanceof DateTimeInterface) {
            return Carbon::instance($dateTime);
        }

        if (is_string($dateTime)) {
            return Carbon::parse($dateTime);
        }
    }
}
