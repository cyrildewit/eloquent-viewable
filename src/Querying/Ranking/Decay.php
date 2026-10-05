<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use DateTimeZone;

/**
 * The weights of a trending window, one per step, worked out in PHP so the
 * SQL only has to sort each view into its step. The rules that keep a score
 * the same on every driver and every source live here, out of reach of a
 * curve:
 *
 * - The weights are integers, the curve's weight times 1,000,000.
 * - The steps start at the floor of the anchor and walk back one step at a
 *   time, on the clock of the zone handed in. An hourly or daily rollup
 *   bucket of that zone then lies inside exactly one step.
 * - The number of steps is capped, so the SQL stays a bounded size.
 */
final readonly class Decay
{
    public const int Scale = 1_000_000;

    /** @param  list<Step>  $steps */
    private function __construct(
        private DecayCurve $curve,
        private Granularity $step,
        private CarbonImmutable $start,
        private ?CarbonImmutable $end,
        private array $steps,
        private int $window,
    ) {}

    /**
     * Resolves the `auto` step and the window from the query's period. The
     * window is the period, or the curve's horizon when the period has no
     * start. Ages are measured from the anchor, the end of the period or now.
     *
     * @param  ?Granularity  $step  an hour or a day, null to pick one
     *
     * @throws InvalidDecay
     */
    public static function for(ViewsQuery $query, DecayCurve $curve, ?Granularity $step, int $maxSteps, DateTimeZone $timezone): self
    {
        $end = CarbonImmutable::instance($query->period?->getEndDateTime() ?? CarbonImmutable::now());
        $periodStart = $query->period?->getStartDateTime();
        $start = $periodStart instanceof CarbonInterface ? CarbonImmutable::instance($periodStart)->min($end) : $end->sub($curve->horizon());

        $step ??= self::auto($start, $end, $maxSteps, $timezone);
        $count = self::count($step, $start, $end, $timezone);

        if ($count > $maxSteps) {
            throw InvalidDecay::producesTooManySteps($count, $maxSteps);
        }

        $steps = self::weigh($curve, $step, $end, $count, $timezone);
        $window = (int) $start->diffInSeconds($end, absolute: true);

        if (! $periodStart instanceof CarbonInterface) {
            $start = $steps === [] ? $end : array_last($steps)->start;
        }

        $periodEnd = $query->period?->getEndDateTime();

        return new self($curve, $step, $start, $periodEnd instanceof CarbonInterface ? $end : null, $steps, $window);
    }

    /**
     * Newest first. A step whose weight rounds to 0 is left out.
     *
     * @return list<Step>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function step(): Granularity
    {
        return $this->step;
    }

    /**
     * The query narrowed to the window, so a count reads only the views a
     * score could weigh. Without an end to the period the window stays open,
     * like the period, so a view recorded this second still counts.
     *
     * @throws InvalidPeriod
     */
    public function narrow(ViewsQuery $query): ViewsQuery
    {
        return $query->withPeriod(Period::create($this->start, $this->end));
    }

    /**
     * The window is measured before it is aligned to the steps, so the
     * identity never includes now, and a remembered ranking survives the
     * clock moving.
     */
    public function identity(): string
    {
        return "{$this->curve->identity()}:{$this->step->value}:{$this->window}";
    }

    private static function auto(CarbonImmutable $start, CarbonImmutable $end, int $maxSteps, DateTimeZone $timezone): Granularity
    {
        if (self::count(Granularity::Hour, $start, $end, $timezone) <= $maxSteps) {
            return Granularity::Hour;
        }

        return Granularity::Day;
    }

    private static function count(Granularity $step, CarbonImmutable $start, CarbonImmutable $end, DateTimeZone $timezone): int
    {
        if ($start >= $end) {
            return 0;
        }

        return $step->countBetween($start->setTimezone($timezone), $end->setTimezone($timezone));
    }

    /**
     * The newest step starts at the floor of the anchor, or a step before it
     * when the anchor falls on an edge, so it is never empty. The step of
     * age `k` weighs as a view `k` steps old.
     *
     * @return list<Step>
     *
     * @throws InvalidDecay
     */
    private static function weigh(DecayCurve $curve, Granularity $step, CarbonImmutable $end, int $count, DateTimeZone $timezone): array
    {
        $local = $end->setTimezone($timezone);
        $newest = $step->floor($local);

        if ($newest->equalTo($local)) {
            $newest = $step->add($newest, -1);
        }

        $application = Timezone::application();
        $steps = [];

        for ($age = 0; $age < $count; $age++) {
            $weight = self::weight($curve, $step, $age);

            if ($weight === 0) {
                continue;
            }

            $steps[] = new Step(CarbonImmutable::instance($step->add($newest, -$age))->setTimezone($application), $weight);
        }

        return $steps;
    }

    /** @throws InvalidDecay */
    private static function weight(DecayCurve $curve, Granularity $step, int $age): int
    {
        $weight = $curve->weight($step === Granularity::Hour ? CarbonInterval::hours($age) : CarbonInterval::days($age));

        if (is_nan($weight) || $weight < 0 || $weight > 1) {
            throw InvalidDecay::weightOutOfRange($curve, $weight);
        }

        return (int) round($weight * self::Scale);
    }
}
