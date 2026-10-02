<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Php;

use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use Generator;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * `ViewSeries::fill()`: the PHP side of `countByInterval()`, which walks a
 * cursor over the period and builds a bucket per step. Pure PHP, no
 * application, so it runs anywhere. The largest case is a year of hours,
 * close to the default `max_intervals`.
 */
#[Groups(['php'])]
#[OutputTimeUnit('milliseconds', 3)]
#[Revs(10)]
#[Iterations(5)]
final class ViewSeriesBench
{
    private const string ANCHOR = '2026-01-01 00:00:00';

    /**
     * @return Generator<string, array{hours: int}>
     */
    public function provideSizes(): Generator
    {
        yield '168 buckets' => ['hours' => 168];
        yield '720 buckets' => ['hours' => 720];
        yield '8,760 buckets' => ['hours' => 8_760];
    }

    /**
     * @param  array{hours: int}  $params
     */
    #[ParamProviders('provideSizes')]
    public function benchFill(array $params): void
    {
        $end = new \DateTimeImmutable(self::ANCHOR, new \DateTimeZone('UTC'));
        $start = $end->modify("-{$params['hours']} hours");

        ViewSeries::fill(Period::create($start, $end), Granularity::Hour, []);
    }
}
