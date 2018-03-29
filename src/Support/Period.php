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
    const PAST_DAYS = 'PAST_DAYS';
    const PAST_WEEKS = 'PAST_WEEKS';
    const PAST_MONTHS = 'PAST_MONTHS';
    const PAST_YEARS = 'PAST_YEARS';

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

    /** */
    public function hasFixedDateTimes()
    {
        return $this->fixedDateTimes;
    }

    public function setStartDateTime(DateTime $startDateTime)
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function setEndDateTime(DateTime $startDateTime)
    {
        $this->endDateTime = $endDateTime;

        return $this;
    }

    public function setfixedDateTimes(bool $status)
    {
        $this->fixedDateTimes = $status;

        return $this;
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

        $subTypeExploded = explode('_', strtolower($this->subType));

        $subType = $subTypeExploded[0];
        $subValueType = $subTypeExploded[1];

        return "{$subType}{$this->subValue}{$subValueType}|";
    }

    public function setSubType($subType)
    {
        $this->subType = $subType;

        return $this;
    }

    public function setSubValue($subValue)
    {
        $this->subValue = $subValue;

        return $this;
    }

    /**
     * @return
     */
    public static function pastDays(int $days)
    {
        return self::past(self::PAST_DAYS, $days);
    }

    /**
     * @return
     */
    public static function pastWeeks(int $weeks)
    {
        return self::past(self::PAST_WEEKS, $weeks);
    }

    /**
     * @return
     */
    public static function pastMonths(int $months)
    {
        return self::past(self::PAST_MONTHS, $months);
    }

    /**
     * @return
     */
    public static function pastYears(int $years)
    {
        return self::past(self::PAST_YEARS, $years);
    }

    public static function subSeconds(int $seconds)
    {
        return self::sub(self::SUB_SECONDS, $seconds);
    }

    public static function subMinutes(int $minutes)
    {
        return self::sub(self::SUB_MINUTES, $minutes);
    }

    public static function subHours(int $hours)
    {
        return self::sub(self::SUB_HOURS, $hours);
    }

    public static function subDays(int $days)
    {
        return self::sub(self::SUB_DAYS, $days);
    }

    public static function subWeeks(int $weeks)
    {
        return self::sub(self::SUB_WEEKS, $weeks);
    }

    public static function subMonths(int $months)
    {
        return self::sub(self::SUB_MONTHS, $months);
    }

    public static function subYears(int $years)
    {
        return self::sub(self::SUB_YEARS, $years);
    }

    /**
     * @return
     */
    public static function past(string $pastType, int $subValue)
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(str_after($pastType, 'PAST_')));
        $today = Carbon::today();

        if (! is_callable([$today, $subTypeMethod])) {
            return false;
        }

        $startDateTime = $today->$subTypeMethod($subValue);

        $period = new static($startDateTime);

        return $period->setfixedDateTimes(false)
            ->setSubType($pastType)
            ->setSubValue($subValue);
    }

    /**
     * @return
     */
    public static function sub(string $subType, int $subValue)
    {
        $subTypeMethod = 'sub'.ucfirst(strtolower(str_after($subType, 'SUB_')));
        $now = Carbon::now();

        if (! is_callable([$now, $subTypeMethod])) {
            return false;
        }

        $startDateTime = $now->$subTypeMethod($subValue);

        $period = new static($startDateTime);

        return $period->setfixedDateTimes(false)
            ->setSubType($subType)
            ->setSubValue($subValue);
    }
}
