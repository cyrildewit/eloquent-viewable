<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Querying;

use CyrildeWit\EloquentViewable\Benchmarks\Support\BenchCase;
use CyrildeWit\EloquentViewable\Support\Granularity;
use Generator;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * `views($viewable)->countByInterval()`: a `group by` on a date expression
 * over `viewed_at`. No index serves the grouping, so the database range
 * scans the period and aggregates every row in it. The ranges cover a week
 * of hours up to two years of months. `benchCountByIntervalInTimezone` runs
 * the same ranges in a zone with daylight saving time, where the bucket
 * expression shifts `viewed_at` by a different offset on each side of every
 * transition: one shift for the ranges inside winter time, a `case` over
 * two to four transitions for the year and longer.
 */
#[Groups(['read'])]
#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(3)]
#[Iterations(5)]
final class CountViewsByIntervalBench extends BenchCase
{
    private const string TIMEZONE = 'Europe/Amsterdam';

    /**
     * @return Generator<string, array{target: string}>
     */
    public function provideSeriesTargets(): Generator
    {
        yield 'hot article' => ['target' => 'hot'];
        yield 'all articles' => ['target' => 'type'];
    }

    /**
     * @return Generator<string, array{days: int, granularity: string}>
     */
    public function provideRanges(): Generator
    {
        yield '7 days by hour' => ['days' => 7, 'granularity' => Granularity::Hour->value];
        yield '30 days by hour' => ['days' => 30, 'granularity' => Granularity::Hour->value];
        yield '1 year by day' => ['days' => 365, 'granularity' => Granularity::Day->value];
        yield '2 years by week' => ['days' => 730, 'granularity' => Granularity::Week->value];
        yield '2 years by month' => ['days' => 730, 'granularity' => Granularity::Month->value];
    }

    /**
     * @param  array{target: string, days: int, granularity: string}  $params
     */
    #[ParamProviders(['provideSeriesTargets', 'provideRanges'])]
    public function benchCountByInterval(array $params): void
    {
        views($this->target($params))
            ->period($this->period($params))
            ->countByInterval(Granularity::from($params['granularity']));
    }

    /**
     * @param  array{target: string, days: int, granularity: string}  $params
     */
    #[ParamProviders(['provideSeriesTargets', 'provideRanges'])]
    public function benchUniqueCountByInterval(array $params): void
    {
        views($this->target($params))
            ->period($this->period($params))
            ->unique()
            ->countByInterval(Granularity::from($params['granularity']));
    }

    /**
     * @param  array{target: string, days: int, granularity: string}  $params
     */
    #[ParamProviders(['provideSeriesTargets', 'provideRanges'])]
    public function benchCountByIntervalInTimezone(array $params): void
    {
        views($this->target($params))
            ->period($this->period($params))
            ->timezone(self::TIMEZONE)
            ->countByInterval(Granularity::from($params['granularity']));
    }
}
