<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Granularity;

describe('add', function (): void {
    it('adds one {granularity}', function (Granularity $granularity, string $from, string $expected): void {
        expect($granularity->add(Carbon::parse($from), 1)->format('Y-m-d H:i:s'))->toBe($expected);
    })->with([
        'hour' => [Granularity::Hour, '2026-03-01 10:00:00', '2026-03-01 11:00:00'],
        'day' => [Granularity::Day, '2026-03-01 00:00:00', '2026-03-02 00:00:00'],
        'week' => [Granularity::Week, '2026-03-02 00:00:00', '2026-03-09 00:00:00'],
        'month' => [Granularity::Month, '2026-03-01 00:00:00', '2026-04-01 00:00:00'],
        'year' => [Granularity::Year, '2026-03-01 00:00:00', '2027-03-01 00:00:00'],
    ]);

    it('adds several buckets at once', function (): void {
        expect(Granularity::Day->add(Carbon::parse('2026-03-01'), 3)->format('Y-m-d'))->toBe('2026-03-04');
    });

    it('does not mutate the date it is given', function (): void {
        $dateTime = Carbon::parse('2026-03-01 00:00:00');

        Granularity::Day->add($dateTime, 1);

        expect($dateTime->format('Y-m-d H:i:s'))->toBe('2026-03-01 00:00:00');
    });
});

describe('floor', function (): void {
    it('snaps to the start of the {granularity}', function (Granularity $granularity, string $expected): void {
        expect($granularity->floor(Carbon::parse('2026-03-04 10:37:12'))->format('Y-m-d H:i:s'))->toBe($expected);
    })->with([
        'hour' => [Granularity::Hour, '2026-03-04 10:00:00'],
        'day' => [Granularity::Day, '2026-03-04 00:00:00'],
        'week' => [Granularity::Week, '2026-03-02 00:00:00'],
        'month' => [Granularity::Month, '2026-03-01 00:00:00'],
        'year' => [Granularity::Year, '2026-01-01 00:00:00'],
    ]);

    it('starts weeks on Monday regardless of the locale', function (): void {
        $locale = Carbon::getLocale();

        try {
            Carbon::setLocale('en_US');

            // A Sunday. A Sunday-first locale would floor it to itself.
            expect(Granularity::Week->floor(Carbon::parse('2026-03-08 15:00:00'))->format('Y-m-d H:i:s'))
                ->toBe('2026-03-02 00:00:00');
        } finally {
            Carbon::setLocale($locale);
        }
    });

    it('does not mutate the date it is given', function (): void {
        $dateTime = Carbon::parse('2026-03-04 10:37:12');

        Granularity::Day->floor($dateTime);

        expect($dateTime->format('Y-m-d H:i:s'))->toBe('2026-03-04 10:37:12');
    });
});

describe('label format', function (): void {
    it('formats a {granularity} label down to the bucket width', function (Granularity $granularity, string $expected): void {
        expect(Carbon::parse('2026-03-02 10:00:00')->format($granularity->labelFormat()))->toBe($expected);
    })->with([
        'hour' => [Granularity::Hour, '2026-03-02 10:00'],
        'day' => [Granularity::Day, '2026-03-02'],
        'week' => [Granularity::Week, '2026-03-02'],
        'month' => [Granularity::Month, '2026-03'],
        'year' => [Granularity::Year, '2026'],
    ]);
});

describe('count between', function (): void {
    it('counts the buckets from the floored start up to the exclusive end', function (Granularity $granularity, string $start, string $end, int $expected): void {
        expect($granularity->countBetween(Carbon::parse($start), Carbon::parse($end)))->toBe($expected);
    })->with([
        'a month of days' => [Granularity::Day, '2026-09-01 00:00:00', '2026-10-01 00:00:00', 30],
        'an unaligned start floors' => [Granularity::Day, '2026-09-01 10:30:00', '2026-09-05 00:00:00', 4],
        'an end inside a bucket counts it' => [Granularity::Day, '2026-09-01 00:00:00', '2026-09-05 10:00:00', 5],
        'a day of hours' => [Granularity::Hour, '2026-09-01 00:00:00', '2026-09-02 00:00:00', 24],
        'one month' => [Granularity::Month, '2026-09-15 00:00:00', '2026-10-01 00:00:00', 1],
        'a month and a bit' => [Granularity::Month, '2026-09-15 00:00:00', '2026-10-01 10:00:00', 2],
        'one week from a wednesday' => [Granularity::Week, '2026-03-04 00:00:00', '2026-03-09 00:00:00', 1],
        'two years' => [Granularity::Year, '2026-06-01 00:00:00', '2028-01-01 00:00:00', 2],
        'equal bounds' => [Granularity::Day, '2026-09-01 00:00:00', '2026-09-01 00:00:00', 0],
    ]);

    it('counts on the wall clock across a DST transition', function (): void {
        // October 2026 has 31 days and one of them lasts 25 hours in Amsterdam.
        $start = Carbon::parse('2026-10-01 00:00:00', 'Europe/Amsterdam');
        $end = Carbon::parse('2026-11-01 00:00:00', 'Europe/Amsterdam');

        expect(Granularity::Day->countBetween($start, $end))->toBe(31);
    });
});
