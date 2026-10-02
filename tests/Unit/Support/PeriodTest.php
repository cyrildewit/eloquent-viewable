<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Period;

it('can be constructed without arguments', function (): void {
    $period = new Period;

    expect($period->getStartDateTime())->toBeNull()
        ->and($period->getEndDateTime())->toBeNull();
});

it('can construct a new period instance', function (): void {
    $startDateTime = Carbon::yesterday();
    $endDateTime = Carbon::today();

    $period = new Period($startDateTime, $endDateTime);

    expect($period->getStartDateTime())->toEqual($startDateTime)
        ->and($period->getEndDateTime())->toEqual($endDateTime);
});

it('can construct a new period instance with strings as arguments', function (): void {
    $period = new Period('2018-07-16', '2018-12-23');

    expect($period->getStartDateTime())->toEqual(Carbon::parse('2018-07-16'))
        ->and($period->getEndDateTime())->toEqual(Carbon::parse('2018-12-23'));
});

it('can construct a new period instance with start datetime argument as string', function (): void {
    $period = new Period('2018-07-16');

    expect($period->getStartDateTime())->toEqual(Carbon::parse('2018-07-16'))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

it('can construct a new period instance with end datetime argument as string', function (): void {
    $period = new Period(null, '2018-07-16');

    expect($period->getStartDateTime())->not->toBeInstanceOf(CarbonInterface::class)
        ->and($period->getEndDateTime())->toEqual(Carbon::parse('2018-07-16'));
});

it('does not throw when the start and end date times are equal', function (): void {
    $dateTime = Carbon::parse('2020-01-01');

    expect(fn (): Period => new Period($dateTime, $dateTime))->not->toThrow(InvalidPeriod::class);
});

it('will throw an exception if the start date time comes after the end date time', function (): void {
    expect(fn (): Period => new Period(Carbon::create(2018), Carbon::create(2017)))
        ->toThrow(InvalidPeriod::class);
});

it('is immutable', function (): void {
    expect(new ReflectionClass(Period::class))->isFinal()->toBeTrue();
});

test('static create can construct a new period instance', function (): void {
    $startDateTime = Carbon::yesterday();
    $endDateTime = Carbon::today();

    $period = Period::create($startDateTime, $endDateTime);

    expect($period->getStartDateTime())->toEqual($startDateTime)
        ->and($period->getEndDateTime())->toEqual($endDateTime);
});

test('static since can construct a new period instance', function (): void {
    $startDateTime = Carbon::yesterday();

    $period = Period::since($startDateTime);

    expect($period->getStartDateTime())->toEqual($startDateTime)
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static upto can construct a new period instance', function (): void {
    $endDateTime = Carbon::yesterday();

    $period = Period::upto($endDateTime);

    expect($period->getStartDateTime())->not->toBeInstanceOf(CarbonInterface::class)
        ->and($period->getEndDateTime())->toEqual($endDateTime);
});

test('static past {method} can construct a new period instance', function (string $periodMethod, string $carbonMethod, int $value): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::{$periodMethod}($value);

    expect($period->getStartDateTime())->toEqual(Carbon::today()->{$carbonMethod}($value))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
})->with([
    'days' => ['pastDays', 'subDays', 5],
    'weeks' => ['pastWeeks', 'subWeeks', 2],
    'months' => ['pastMonths', 'subMonths', 2],
    'years' => ['pastYears', 'subYears', 2],
]);

test('static sub {method} can construct a new period instance', function (string $method): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::{$method}(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->{$method}(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
})->with([
    'subSeconds', 'subMinutes', 'subHours', 'subDays', 'subWeeks', 'subMonths', 'subYears',
]);

test('relative periods expose a stable cache signature', function (string $method, int $value, string $signature): void {
    expect(Period::{$method}($value)->cacheSignature())->toBe($signature);
})->with([
    ['pastDays', 3, 'past3days'],
    ['pastWeeks', 2, 'past2weeks'],
    ['pastMonths', 6, 'past6months'],
    ['pastYears', 1, 'past1years'],
    ['subSeconds', 34, 'sub34seconds'],
    ['subMinutes', 5, 'sub5minutes'],
    ['subHours', 12, 'sub12hours'],
    ['subDays', 7, 'sub7days'],
    ['subWeeks', 3, 'sub3weeks'],
    ['subMonths', 2, 'sub2months'],
    ['subYears', 4, 'sub4years'],
]);

test('absolute periods expose a timestamp-based cache signature', function (): void {
    $start = Carbon::yesterday();
    $end = Carbon::today();

    expect(Period::create($start, $end)->cacheSignature())
        ->toBe("{$start->timestamp}-{$end->timestamp}");
});

describe('timezone', function (): void {
    it('converts bounds to the application timezone', function (): void {
        $period = Period::create(
            Carbon::parse('2026-09-27 00:00:00', 'Europe/Amsterdam'),
            Carbon::parse('2026-09-28 00:00:00', 'Europe/Amsterdam'),
        );

        expect($period->getStartDateTime()->getTimezone()->getName())->toBe(date_default_timezone_get())
            ->and($period->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2026-09-26 22:00:00')
            ->and($period->getEndDateTime()->getTimezone()->getName())->toBe(date_default_timezone_get())
            ->and($period->getEndDateTime()->format('Y-m-d H:i:s'))->toBe('2026-09-27 22:00:00');
    });

    it('parses string bounds in the application timezone', function (): void {
        $period = Period::create('2026-09-27 00:00:00');

        expect($period->getStartDateTime()->getTimezone()->getName())->toBe(date_default_timezone_get())
            ->and($period->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2026-09-27 00:00:00');
    });

    it('does not mutate the bounds it is given', function (): void {
        $start = Carbon::parse('2026-09-27 00:00:00', 'Europe/Amsterdam');

        Period::create($start);

        expect($start->getTimezone()->getName())->toBe('Europe/Amsterdam');
    });
});

describe('relative periods in a timezone', function (): void {
    beforeEach(function (): void {
        // 23:00 UTC on the 1st is 09:00 on the 2nd in Sydney.
        Carbon::setTestNow('2026-09-01 23:00:00');
    });

    it('anchors a past period on midnight of that zone', function (): void {
        $period = Period::pastDays(7, 'Australia/Sydney');

        expect($period->getStartDateTime()->getTimezone()->getName())->toBe(date_default_timezone_get())
            ->and($period->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-08-26 00:00:00', 'Australia/Sydney')->timestamp)
            ->and(Period::pastDays(7)->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-08-25 00:00:00', 'UTC')->timestamp);
    });

    it('anchors a sub period on now, which is the same instant in every zone', function (): void {
        expect(Period::subHours(3, 'Australia/Sydney')->getStartDateTime()->timestamp)
            ->toBe(Period::subHours(3)->getStartDateTime()->timestamp);
    });

    it('accepts a DateTimeZone', function (): void {
        expect(Period::pastDays(1, new DateTimeZone('Australia/Sydney'))->getStartDateTime()->toIso8601String())
            ->toBe(Period::pastDays(1, 'Australia/Sydney')->getStartDateTime()->toIso8601String());
    });

    it('rejects a timezone that is not an identifier', function (): void {
        expect(fn (): Period => Period::pastDays(7, '+10:00'))->toThrow(InvalidTimezone::class);
    });

    it('includes the zone in the cache signature', function (): void {
        expect(Period::pastDays(7, 'Australia/Sydney')->cacheSignature())->toBe('past7days@Australia/Sydney')
            ->and(Period::pastDays(7)->cacheSignature())->toBe('past7days');
    });
});
