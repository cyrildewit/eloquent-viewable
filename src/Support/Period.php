<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Support;

use DateTime;
use Carbon\Carbon;

/**
 * Class Period.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
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

    /**
     * @var \DateTime|null
     */
    protected $startDateTime;

    /**
     * @var \DateTime|null
     */
    protected $endDateTime;

    /**
     * @var bool
     */
    protected $fixedDateTimes = true;

    /**
     * @var string
     */
    protected $subType;

    /**
     * @var int
     */
    protected $subValue;

    /**
     * Create a new Period instance.
     *
     * @param  \Datetime  $startDateTime
     * @param  \Datetime  $endDateTime
     * @return CyrildeWit\EloquentViewable\Period
     */
    public function __construct(DateTime $startDateTime = null, DateTime $endDateTime = null)
    {
        if ($startDateTime instanceof DateTime && $endDateTime instanceof DateTime) {
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
     * @param  \Datetime  $startDateTime
     * @param  \Datetime  $endDateTime
     * @return CyrildeWit\EloquentViewable\Period
     */
    public static function create(DateTime $startDateTime = null, DateTime $endDateTime = null): self
    {
        return new static($startDateTime, $endDateTime);
    }

    /**
     * Get the start date time.
     *
     * @return \DateTime|null
     */
    public function getStartDateTime()
    {
        return $this->startDateTime;
    }

    /**
     * Get the end date time.
     *
     * @return \DateTime|null
     */
    public function getEndDateTime()
    {
        return $this->endDateTime;
    }

    /**
     * Check if the period has fixed date times.
     *
     * @return bool
     */
    public function hasFixedDateTimes(): bool
    {
        return $this->fixedDateTimes;
    }

    /**
     * Get the DateTime string of the start date time.
     *
     * @return string
     */
    public function getStartDateTimeString(): string
    {
        return $this->startDateTime !== null ? $this->startDateTime->toDateTimeString() : '';
    }

    /**
     * Get the DateTime string of the start date time.
     *
     * @return string
     */
    public function getEndDateTimeString(): string
    {
        return $this->endDateTime !== null ? $this->endDateTime->toDateTimeString() : '';
    }

    /**
     * Make a unique key.
     *
     * @return string
     */
    public function makeKey(): string
    {
        if ($this->hasFixedDateTimes()) {
            return "{$this->getStartDateTimeString()}|{$this->getEndDateTimeString()}";
        }

        list($subType, $subValueType) = explode('_', strtolower($this->subType));

        return "{$subType}{$this->subValue}{$subValueType}|";
    }

    /**
     * Set the start date time.
     *
     * @param  \DateTime  $startDateTime
     * @return $this
     */
    public function setStartDateTime(DateTime $startDateTime)
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    /**
     * Set the end date time.
     *
     * @param  \DateTime  $endDateTime
     * @return $this
     */
    public function setEndDateTime(DateTime $endDateTime)
    {
        $this->endDateTime = $endDateTime;

        return $this;
    }

    /**
     * Set the fixedDateTimes property.
     *
     * @param  bool  $status
     * @return $this
     */
    public function setfixedDateTimes(bool $status)
    {
        $this->fixedDateTimes = $status;

        return $this;
    }

    /**
     * Set the sub type.
     *
     * @param  string  $subType
     * @return $this
     */
    public function setSubType(string $subType)
    {
        $this->subType = $subType;

        return $this;
    }

    /**
     * Set the sub value.
     *
     * @param  string  $subValue
     * @return $this
     */
    public function setSubValue($subValue)
    {
        $this->subValue = $subValue;

        return $this;
    }

    /**
     * Create a new Period instance with a start date time of today minus the given days.
     *
     * Start Date Time: Carbon::today()->subDays(2);
     *
     * @param  int  $days
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function pastDays(int $days): self
    {
        return self::subToday(self::PAST_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given weeks.
     *
     * Start Date Time: Carbon::today()->subWeeks(2);
     *
     * @param  int  $weeks
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function pastWeeks(int $weeks)
    {
        return self::subToday(self::PAST_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given months.
     *
     * Start Date Time: Carbon::today()->subMonths(2);
     *
     * @param  int  $months
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function pastMonths(int $months): self
    {
        return self::subToday(self::PAST_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given years.
     *
     * Start Date Time: Carbon::today()->subYears(2);
     *
     * @param  int  $years
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function pastYears(int $years): self
    {
        return self::subToday(self::PAST_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given seconds.
     *
     * Start Date Time: Carbon::now()->subSeconds(2);
     *
     * @param  int  $seconds
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subSeconds(int $seconds): self
    {
        return self::subNow(self::SUB_SECONDS, $seconds);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given minutes.
     *
     * Start Date Time: Carbon::now()->subMinutes(2);
     *
     * @param  int  $minutes
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subMinutes(int $minutes): self
    {
        return self::subNow(self::SUB_MINUTES, $minutes);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given hours.
     *
     * Start Date Time: Carbon::now()->subHours(2);
     *
     * @param  int  $hours
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subHours(int $hours): self
    {
        return self::subNow(self::SUB_HOURS, $hours);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given days.
     *
     * Start Date Time: Carbon::now()->subDays(2);
     *
     * @param  int  $days
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subDays(int $days): self
    {
        return self::subNow(self::SUB_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given weeks.
     *
     * Start Date Time: Carbon::now()->subWeeks(2);
     *
     * @param  int  $weeks
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subWeeks(int $weeks): self
    {
        return self::subNowsub(self::SUB_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given months.
     *
     * Start Date Time: Carbon::now()->subMonths(2);
     *
     * @param  int  $months
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subMonths(int $months): self
    {
        return self::subNow(self::SUB_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given years.
     *
     * Start Date Time: Carbon::now()->suYears(2);
     *
     * @param  int  $years
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subYears(int $years): self
    {
        return self::subNow(self::SUB_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given subType.
     *
     * Start Date Time: Carbon::today()->sub<subType>(<subValue>);
     *
     * @param  string  $subType
     * @param  int  $subValue
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subToday(string $subType, int $subValue): self
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(str_after($subType, 'PAST_')));
        $today = Carbon::today();

        return self::sub($today, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given subType.
     *
     * Start Date Time: Carbon::now()->sub<subType>(<subValue>);
     *
     * @param  string  $subType
     * @param  int  $subValue
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function subNow(string $subType, int $subValue): self
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(str_after($subType, 'SUB_')));
        $now = Carbon::now();

        return self::sub($now, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of startDateTime minus the given subType.
     *
     * Start Date Time: <startDateTime>->sub<subType>(<subValue>);
     *
     * @param  \DateTime  $startDateTime
     * @param  string  $subTypeMethod
     * @param  string  $subType
     * @param  int  $subValue
     * @return \CyrildeWit\EloquentViewable\Support\Period
     */
    public static function sub(DateTime $startDateTime, string $subTypeMethod, string $subType, int $subValue): self
    {
        if (! is_callable([$startDateTime, $subTypeMethod])) {
            return false;
        }

        $startDateTime = $startDateTime->$subTypeMethod($subValue);

        $period = new static($startDateTime);

        return $period->setfixedDateTimes(false)
            ->setSubType($subType)
            ->setSubValue($subValue);
    }
}
