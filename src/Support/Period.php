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
    const string PAST_DAYS = 'PAST_DAYS';

    const string PAST_WEEKS = 'PAST_WEEKS';

    const string PAST_MONTHS = 'PAST_MONTHS';

    const string PAST_YEARS = 'PAST_YEARS';

    const string SUB_SECONDS = 'SUB_SECONDS';

    const string SUB_MINUTES = 'SUB_MINUTES';

    const string SUB_HOURS = 'SUB_HOURS';

    const string SUB_DAYS = 'SUB_DAYS';

    const string SUB_WEEKS = 'SUB_WEEKS';

    const string SUB_MONTHS = 'SUB_MONTHS';

    const string SUB_YEARS = 'SUB_YEARS';

    protected ?CarbonInterface $startDateTime;

    protected ?CarbonInterface $endDateTime;

    protected bool $fixedDateTimes = true;

    protected ?string $subType;

    protected ?int $subValue;

    /**
     * @throws InvalidPeriod
     */
    public function __construct(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null,
    ) {
        $this->startDateTime = $this->resolveDateTime($startDateTime);
        $this->endDateTime = $this->resolveDateTime($endDateTime);

        $this->guardChronologicalOrder();
    }

    /**
     * @throws InvalidPeriod
     */
    public static function create(
        DateTimeInterface|string|null $startDateTime = null,
        DateTimeInterface|string|null $endDateTime = null
    ): static {
        return new static($startDateTime, $endDateTime);
    }

    /**
     * @throws InvalidPeriod
     */
    public static function since(DateTimeInterface|string|null $startDateTime = null): static
    {
        return new static($startDateTime);
    }

    /**
     * @throws InvalidPeriod
     */
    public static function upto(DateTimeInterface|string|null $endDateTime = null): static
    {
        return new static(null, $endDateTime);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given days.
     *
     * Start Date Time: Carbon::today()->subDays(2);
     */
    public static function pastDays(int $days): static
    {
        return self::subToday(self::PAST_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given weeks.
     *
     * Start Date Time: Carbon::today()->subWeeks(2);
     */
    public static function pastWeeks(int $weeks): static
    {
        return self::subToday(self::PAST_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given months.
     *
     * Start Date Time: Carbon::today()->subMonths(2);
     */
    public static function pastMonths(int $months): static
    {
        return self::subToday(self::PAST_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given years.
     *
     * Start Date Time: Carbon::today()->subYears(2);
     */
    public static function pastYears(int $years): static
    {
        return self::subToday(self::PAST_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given seconds.
     *
     * Start Date Time: Carbon::now()->subSeconds(2);
     */
    public static function subSeconds(int $seconds): static
    {
        return self::subNow(self::SUB_SECONDS, $seconds);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given minutes.
     *
     * Start Date Time: Carbon::now()->subMinutes(2);
     */
    public static function subMinutes(int $minutes): static
    {
        return self::subNow(self::SUB_MINUTES, $minutes);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given hours.
     *
     * Start Date Time: Carbon::now()->subHours(2);
     */
    public static function subHours(int $hours): static
    {
        return self::subNow(self::SUB_HOURS, $hours);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given days.
     *
     * Start Date Time: Carbon::now()->subDays(2);
     */
    public static function subDays(int $days): static
    {
        return self::subNow(self::SUB_DAYS, $days);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given weeks.
     *
     * Start Date Time: Carbon::now()->subWeeks(2);
     */
    public static function subWeeks(int $weeks): static
    {
        return self::subNow(self::SUB_WEEKS, $weeks);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given months.
     *
     * Start Date Time: Carbon::now()->subMonths(2);
     */
    public static function subMonths(int $months): static
    {
        return self::subNow(self::SUB_MONTHS, $months);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given years.
     *
     * Start Date Time: Carbon::now()->suYears(2);
     */
    public static function subYears(int $years): static
    {
        return self::subNow(self::SUB_YEARS, $years);
    }

    /**
     * Create a new Period instance with a start date time of today minus the given subType.
     *
     * Start Date Time: Carbon::today()->sub<subType>(<subValue>);
     */
    public static function subToday(string $subType, int $subValue): static
    {
        $today = Carbon::today();
        $subTypeMethod = 'sub'.ucfirst(strtolower(Str::after($subType, 'PAST_')));

        return self::sub($today, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of now minus the given subType.
     *
     * Start Date Time: Carbon::now()->sub<subType>(<subValue>);
     */
    public static function subNow(string $subType, int $subValue): static
    {
        $now = Carbon::now();
        $subTypeMethod = 'sub'.ucfirst(strtolower(Str::after($subType, 'SUB_')));

        return self::sub($now, $subTypeMethod, $subType, $subValue);
    }

    /**
     * Create a new Period instance with a start date time of startDateTime minus the given subType.
     *
     * Start Date Time: <startDateTime>->sub<subType>(<subValue>);
     *
     * @throws InvalidPeriod
     */
    public static function sub(
        CarbonInterface $startDateTime,
        string $subTypeMethod,
        string $subType,
        int $subValue
    ): static {
        $startDateTime = $startDateTime->$subTypeMethod($subValue);

        $period = new static($startDateTime);

        return $period->setFixedDateTimes(false)
            ->setSubType($subType)
            ->setSubValue($subValue);
    }

    public function getStartDateTime(): ?CarbonInterface
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): ?CarbonInterface
    {
        return $this->endDateTime;
    }

    public function hasFixedDateTimes(): bool
    {
        return $this->fixedDateTimes;
    }

    public function getSubType(): ?string
    {
        return $this->subType;
    }

    public function getSubValue(): ?int
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

    protected function resolveDateTime(DateTimeInterface|string|null $dateTime): ?CarbonInterface
    {
        if ($dateTime === null) {
            return null;
        }

        if ($dateTime instanceof DateTimeInterface) {
            return Carbon::instance($dateTime);
        }

        return Carbon::parse($dateTime);
    }

    /**
     * @throws InvalidPeriod
     */
    protected function guardChronologicalOrder(): void
    {
        if ($this->startDateTime === null || $this->endDateTime === null) {
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
