<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;

it('is immutable', function (): void {
    $reflection = new ReflectionClass(ViewSeries::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('fills every bucket in the period from a sparse map', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01', '2026-09-06'), Granularity::Day, [
        '2026-09-01 00:00:00' => 1,
        '2026-09-04 00:00:00' => 1,
    ]);

    expect($series->intervals)->toHaveCount(5)
        ->and($series->intervals->map(fn (Bucket $bucket): int => $bucket->count)->all())->toBe([1, 0, 0, 1, 0])
        ->and($series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('Y-m-d'))->all())
        ->toBe(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05'])
        ->and($series->granularity)->toBe(Granularity::Day)
        ->and($series->period->getStartDateTime()->format('Y-m-d'))->toBe('2026-09-01');
});

it('floors an unaligned start to the bucket label', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01 10:30:00', '2026-09-03 00:00:00'), Granularity::Day, [
        '2026-09-01 00:00:00' => 3,
    ]);

    expect($series->intervals)->toHaveCount(2)
        ->and($series->intervals->first()->start->format('Y-m-d H:i:s'))->toBe('2026-09-01 00:00:00')
        ->and($series->intervals->first()->count)->toBe(3);
});

it('produces no bucket for an end exactly on a boundary', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01', '2026-09-02'), Granularity::Day, []);

    expect($series->intervals)->toHaveCount(1);
});

it('produces a bucket for an end inside it', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01 00:00:00', '2026-09-02 10:00:00'), Granularity::Day, []);

    expect($series->intervals)->toHaveCount(2)
        ->and($series->intervals->last()->start->format('Y-m-d'))->toBe('2026-09-02');
});

it('walks up to now when the period has no end', function (): void {
    Carbon::setTestNow('2026-09-03 12:00:00');

    $series = ViewSeries::fill(Period::since('2026-09-01'), Granularity::Day, []);

    expect($series->intervals)->toHaveCount(3)
        ->and($series->intervals->last()->start->format('Y-m-d'))->toBe('2026-09-03');
});

it('tiles the buckets so that each end is the next start', function (): void {
    $series = ViewSeries::fill(Period::create('2026-01-01', '2026-06-01'), Granularity::Month, []);

    $starts = $series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('Y-m-d H:i:s'))->all();
    $ends = $series->intervals->map(fn (Bucket $bucket): string => $bucket->end->format('Y-m-d H:i:s'))->all();

    expect($starts)->toBe(['2026-01-01 00:00:00', '2026-02-01 00:00:00', '2026-03-01 00:00:00', '2026-04-01 00:00:00', '2026-05-01 00:00:00'])
        ->and(array_slice($ends, 0, 4))->toBe(array_slice($starts, 1))
        ->and($ends[4])->toBe('2026-06-01 00:00:00');
});

it('emits bucket bounds in the application timezone', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01', '2026-09-02'), Granularity::Day, []);

    expect($series->intervals->first()->start->getTimezone()->getName())->toBe(date_default_timezone_get())
        ->and($series->intervals->first()->end->getTimezone()->getName())->toBe(date_default_timezone_get());
});

it('sums the buckets into a total', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01', '2026-09-04'), Granularity::Day, [
        '2026-09-01 00:00:00' => 2,
        '2026-09-03 00:00:00' => 5,
    ]);

    expect($series->total())->toBe(7);
});

it('iterates over its buckets', function (): void {
    $series = ViewSeries::fill(Period::create('2026-09-01', '2026-09-03'), Granularity::Day, []);

    expect(iterator_to_array($series))->toHaveCount(2)
        ->and(iterator_to_array($series)[0])->toBeInstanceOf(Bucket::class);
});

it('throws when the period has no start', function (): void {
    expect(fn (): ViewSeries => ViewSeries::fill(Period::upto('2026-09-01'), Granularity::Day, []))
        ->toThrow(InvalidInterval::class);
});

describe('daylight saving time', function (): void {
    beforeEach(function (): void {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
    });

    afterEach(function (): void {
        date_default_timezone_set($this->timezone);
    });

    it('emits one bucket per wall-clock hour across the autumn transition', function (): void {
        // 2026-10-25 02:00 CEST becomes 02:00 CET, so the 02:00 label covers two
        // real hours of stored rows. PHP resolves the ambiguous label to its
        // second occurrence, so the bucket object itself is the CET hour.
        $series = ViewSeries::fill(
            Period::create(Carbon::parse('2026-10-25 00:00:00'), Carbon::parse('2026-10-25 04:00:00')),
            Granularity::Hour,
            ['2026-10-25 02:00:00' => 7],
        );

        $labels = $series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('H:i'))->all();
        $ambiguous = $series->intervals[2];

        expect($labels)->toBe(['00:00', '01:00', '02:00', '03:00'])
            ->and($ambiguous->count)->toBe(7)
            ->and($ambiguous->start->format('H:i P'))->toBe('02:00 +01:00')
            ->and($ambiguous->end->format('H:i P'))->toBe('03:00 +01:00');
    });

    it('emits a zero-width bucket for the hour skipped by the spring transition', function (): void {
        // 2026-03-29 02:00 does not exist in Amsterdam. PHP resolves the label to 03:00.
        $series = ViewSeries::fill(
            Period::create(Carbon::parse('2026-03-29 00:00:00'), Carbon::parse('2026-03-29 04:00:00')),
            Granularity::Hour,
            [],
        );

        $skipped = $series->intervals[2];

        expect($series->intervals)->toHaveCount(4)
            ->and($skipped->count)->toBe(0)
            ->and($skipped->start->diffInMinutes($skipped->end))->toBe(0.0);
    });
});
