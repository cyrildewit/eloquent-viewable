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
     * @var \DateTime
     */
    protected $startDateTime;

    /**
     * @var \DateTime
     */
    protected $endDateTime;

    /**
     * @var bool
     */
    protected $staticDateTimes = true;

    /**
     * @var string
     */
    protected $pastType;

    /**
     * @var string
     */
    protected $subType;

    /**
     * Create a new Period instance.
     *
     * @param  \Datetime  $startDateTime
     * @param  \Datetime  $endDateTime
     * @return CyrildeWit\EloquentViewable\Period
     */
    public function __construct(DateTime $startDateTime = null, DateTime $endDateTime = null)
    {
        if ($startDateTime > $endDateTime) {
            throw InvalidPeriod::startDateTimeCannotBeAfterEndDateTime($startDateTime, $endDateTime);
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
     * @return
     */
    public static function past(string $pastType, int $number)
    {
        // $this->staticDateTimes = false;
        // $this->pastType = $pastType;

        $subTypeMethod = 'sub'.ucfirst(strtolower(str_after($pastType, 'PAST_')));
        $today = Carbon::today();

        if (! is_callable([$today::today(), $subTypeMethod])) {
            return false;
        }

        $startDateTime = $today->$subTypeMethod($number);
        $endDateTime = null;

        return new static($startDateTime, $endDateTime);
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
        $this->staticDateTimes = false;
        $this->pastType = self::PAST_WEEKS;

        $today = Carbon::today();

        $startDateTime = $today->copy()->subWeeks($weeks);
        $endDateTime = $today->copy();

        return new static($startDateTime, $endDateTime);
    }

    /**
     * @return
     */
    public static function pastMonths(int $months)
    {
        $this->staticDateTimes = false;
        $this->pastType = self::PAST_MONTHS;

        $today = Carbon::today();

        $startDateTime = $today->copy()->subMonths($months);
        $endDateTime = $today->copy();

        return new static($startDateTime, $endDateTime);
    }

    /**
     * @return
     */
    public static function pastYears(int $years)
    {
        $this->staticDateTimes = false;
        $this->pastType = self::PAST_YEARS;

        $today = Carbon::today();

        $startDateTime = $today->copy()->subYears($years);
        $endDateTime = $today->copy();

        return new static($startDateTime, $endDateTime);
    }

    public static function subSeconds(int $seconds)
    {
        $this->staticDateTimes = false;
        $this->pastType = self::SUB_SECONDS;

        $now = Carbon::now();

        $startDateTime = $now->copy()->subSeconds($seconds);
        $endDateTime = $now->copy();

        return new static($startDateTime, $endDateTime);
    }

    public static function subMinutes(int $minutes)
    {
        $this->staticDateTimes = false;
        $this->pastType = self::SUB_MINUTES;

        $now = Carbon::now();

        $startDateTime = $now->copy()->subMinutes($minutes);
        $endDateTime = $now->copy();

        return new static($startDateTime, $endDateTime);
    }
}
