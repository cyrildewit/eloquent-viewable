<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Growth;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Support\Carbon;

/**
 * The rhythm a baseline follows. A window is compared with the same hours on
 * past days, or on the same day of past weeks, so a quiet night is never
 * measured against a busy afternoon.
 */
enum Seasonality: string
{
    case Day = 'day';

    case Week = 'week';

    /**
     * The same window on each of the past days or weeks, the closest first.
     * A period without an end runs until now.
     *
     * @return non-empty-list<Period>
     *
     * @throws InvalidBaseline
     * @throws InvalidPeriod
     */
    public function references(Period $period, int $samples): array
    {
        if ($samples < 1) {
            throw InvalidBaseline::samplesBelowOne($samples);
        }

        $start = $period->getStartDateTime();

        if (! $start instanceof CarbonInterface) {
            throw InvalidBaseline::withoutStart();
        }

        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($period->getEndDateTime() ?? Carbon::now());

        if ($this->back($end, 1) > $start) {
            throw InvalidBaseline::windowLongerThanSeason($this->value);
        }

        $references = [];

        foreach (range(1, $samples) as $sample) {
            $references[] = Period::create($this->back($start, $sample), $this->back($end, $sample));
        }

        return $references;
    }

    private function back(CarbonImmutable $moment, int $times): CarbonImmutable
    {
        return match ($this) {
            self::Day => $moment->subDays($times),
            self::Week => $moment->subWeeks($times),
        };
    }
}
