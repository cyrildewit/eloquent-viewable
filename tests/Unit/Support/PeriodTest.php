<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use Illuminate\Contracts\Routing\UrlRoutable;

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

describe('parse', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow('2026-09-10 12:34:56');
    });

    it('reads the {shorthand} shorthand as the matching constructor', function (string $shorthand, string $method, int $value): void {
        $period = Period::parse($shorthand);

        expect($period->getStartDateTime()->toIso8601String())->toBe(Period::{$method}($value)->getStartDateTime()->toIso8601String())
            ->and($period->getEndDateTime())->toBeNull()
            ->and($period->cacheSignature())->toBe(Period::{$method}($value)->cacheSignature());
    })->with([
        '90s' => ['90s', 'subSeconds', 90],
        '30min' => ['30min', 'subMinutes', 30],
        '12h' => ['12h', 'subHours', 12],
        '7d' => ['7d', 'pastDays', 7],
        '3w' => ['3w', 'pastWeeks', 3],
        '6m' => ['6m', 'pastMonths', 6],
        '1y' => ['1y', 'pastYears', 1],
    ]);

    it('reads a shorthand on the clock of a timezone', function (): void {
        expect(Period::parse('7d', 'Australia/Sydney')->getStartDateTime()->toIso8601String())
            ->toBe(Period::pastDays(7, 'Australia/Sydney')->getStartDateTime()->toIso8601String());
    });

    it('reads a range of two bounds', function (): void {
        $period = Period::parse('2026-01-01..2026-02-01');

        expect($period->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
            ->and($period->getEndDateTime()->format('Y-m-d H:i:s'))->toBe('2026-02-01 00:00:00')
            ->and($period->cacheSignature())->toBe(Period::create('2026-01-01', '2026-02-01')->cacheSignature());
    });

    it('reads bounds with a time of day', function (): void {
        $period = Period::parse('2026-01-01T10:30:00..2026-01-01T12:00:00');

        expect($period->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-01 10:30:00')
            ->and($period->getEndDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-01 12:00:00');
    });

    it('reads an open-ended range on either side', function (): void {
        expect(Period::parse('2026-01-01..')->getStartDateTime()->format('Y-m-d'))->toBe('2026-01-01')
            ->and(Period::parse('2026-01-01..')->getEndDateTime())->toBeNull()
            ->and(Period::parse('..2026-02-01')->getStartDateTime())->toBeNull()
            ->and(Period::parse('..2026-02-01')->getEndDateTime()->format('Y-m-d'))->toBe('2026-02-01');
    });

    it('reads range bounds on the clock of a timezone', function (): void {
        $period = Period::parse('2026-01-01..2026-02-01', 'Australia/Sydney');

        expect($period->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-01-01', 'Australia/Sydney')->timestamp)
            ->and($period->getStartDateTime()->getTimezone()->getName())->toBe(date_default_timezone_get());
    });

    it('rejects a range that runs backwards', function (): void {
        expect(fn (): Period => Period::parse('2026-02-01..2026-01-01'))->toThrow(InvalidPeriod::class, 'cannot be after');
    });

    it('rejects {input}', function (string $input): void {
        expect(fn (): Period => Period::parse($input))->toThrow(InvalidPeriod::class, "`{$input}` is not a period");
    })->with([
        'an empty string' => [''],
        'a word' => ['nonsense'],
        'an unknown unit' => ['7x'],
        'a negative value' => ['-7d'],
        'a bare separator' => ['..'],
        'a range with a word in it' => ['2026-01-01..nonsense'],
        'a range with a relative bound' => ['2026-01-01..tomorrow'],
        'a bound with a space' => ['2026-01-01 10:00:00..2026-02-01'],
        'a bound with a month out of range' => ['2026-13-01..'],
        'two separators' => ['2026-01-01..2026-02-01..2026-03-01'],
    ]);

    it('rejects a timezone that is not an identifier', function (): void {
        expect(fn (): Period => Period::parse('7d', 'CEST'))->toThrow(InvalidTimezone::class);
    });
});

describe('route binding', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow('2026-09-10 12:34:56');
    });

    it('is routable', function (): void {
        expect(new Period)->toBeInstanceOf(UrlRoutable::class)
            ->and((new Period)->getRouteKeyName())->toBe('period');
    });

    it('writes a relative period as its shorthand', function (string $method, int $value, string $key): void {
        expect(Period::{$method}($value)->getRouteKey())->toBe($key)
            ->and(Period::parse($key)->getRouteKey())->toBe($key);
    })->with([
        ['subSeconds', 90, '90s'],
        ['subMinutes', 30, '30min'],
        ['subHours', 12, '12h'],
        ['pastDays', 7, '7d'],
        ['pastWeeks', 3, '3w'],
        ['pastMonths', 6, '6m'],
        ['pastYears', 1, '1y'],
    ]);

    it('writes a relative period without a shorthand as its bounds', function (): void {
        // subDays() counts from now rather than midnight, which `7d` does not say.
        expect(Period::subDays(7)->getRouteKey())->toBe('2026-09-03T12:34:56..')
            ->and(Period::pastDays(7, 'Australia/Sydney')->getRouteKey())->toBe('7d');
    });

    it('writes an absolute period as its bounds', function (): void {
        expect(Period::create('2026-01-01', '2026-02-01')->getRouteKey())->toBe('2026-01-01..2026-02-01')
            ->and(Period::create('2026-01-01 10:30:00', '2026-02-01')->getRouteKey())->toBe('2026-01-01T10:30:00..2026-02-01')
            ->and(Period::since('2026-01-01')->getRouteKey())->toBe('2026-01-01..')
            ->and(Period::upto('2026-02-01')->getRouteKey())->toBe('..2026-02-01')
            ->and((new Period)->getRouteKey())->toBe('..');
    });

    it('round-trips an absolute period through parse', function (): void {
        foreach ([Period::create('2026-01-01', '2026-02-01'), Period::create('2026-01-01 10:30:00'), Period::upto('2026-02-01 23:59:59')] as $period) {
            expect(Period::parse($period->getRouteKey())->cacheSignature())->toBe($period->cacheSignature());
        }
    });

    it('resolves a route value by parsing it', function (): void {
        $period = (new Period)->resolveRouteBinding('7d');

        expect($period)->toBeInstanceOf(Period::class)
            ->and($period->getRouteKey())->toBe('7d');
    });

    it('resolves nothing for a value it cannot parse', function (): void {
        expect((new Period)->resolveRouteBinding('nonsense'))->toBeNull()
            ->and((new Period)->resolveRouteBinding(7))->toBeNull()
            ->and((new Period)->resolveChildRouteBinding('post', '7d', null))->toBeNull();
    });
});

describe('previous', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow('2026-09-10 12:34:56');
    });

    it('steps a {method} period back by its own unit', function (string $method, int $value, string $start, string $end): void {
        $previous = Period::{$method}($value)->previous();

        expect($previous->getStartDateTime()->format('Y-m-d H:i:s'))->toBe($start)
            ->and($previous->getEndDateTime()->format('Y-m-d H:i:s'))->toBe($end)
            ->and($previous->getEndDateTime())->toEqual(Period::{$method}($value)->getStartDateTime());
    })->with([
        ['pastDays', 7, '2026-08-27 00:00:00', '2026-09-03 00:00:00'],
        ['pastWeeks', 2, '2026-08-13 00:00:00', '2026-08-27 00:00:00'],
        ['pastMonths', 1, '2026-07-10 00:00:00', '2026-08-10 00:00:00'],
        ['pastYears', 1, '2024-09-10 00:00:00', '2025-09-10 00:00:00'],
        ['subSeconds', 90, '2026-09-10 12:31:56', '2026-09-10 12:33:26'],
        ['subMinutes', 30, '2026-09-10 11:34:56', '2026-09-10 12:04:56'],
        ['subHours', 12, '2026-09-09 12:34:56', '2026-09-10 00:34:56'],
        ['subDays', 7, '2026-08-27 12:34:56', '2026-09-03 12:34:56'],
        ['subWeeks', 1, '2026-08-27 12:34:56', '2026-09-03 12:34:56'],
        ['subMonths', 1, '2026-07-10 12:34:56', '2026-08-10 12:34:56'],
        ['subYears', 1, '2024-09-10 12:34:56', '2025-09-10 12:34:56'],
    ]);

    it('keeps stepping back from a previous period', function (): void {
        $period = Period::pastDays(7)->previous()->previous();

        expect($period->getStartDateTime()->format('Y-m-d'))->toBe('2026-08-20')
            ->and($period->getEndDateTime()->format('Y-m-d'))->toBe('2026-08-27');
    });

    it('steps a relative period back on the clock it was built in', function (): void {
        // 12:34 UTC on the 10th is 22:34 on the 10th in Sydney.
        $previous = Period::pastDays(1, 'Australia/Sydney')->previous();

        expect($previous->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-09-08 00:00:00', 'Australia/Sydney')->timestamp)
            ->and($previous->getEndDateTime()->timestamp)->toBe(Carbon::parse('2026-09-09 00:00:00', 'Australia/Sydney')->timestamp);
    });

    it('keeps the shift when re-anchored in a timezone', function (): void {
        $previous = Period::pastDays(1)->previous()->anchoredIn(new Timezone('Australia/Sydney'));

        expect($previous->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-09-08 00:00:00', 'Australia/Sydney')->timestamp)
            ->and($previous->cacheSignature())->toBe('past1days~1@Australia/Sydney');
    });

    it('gives a shifted relative period a stable cache signature', function (): void {
        $signature = Period::pastDays(7)->previous()->cacheSignature();

        Carbon::setTestNow('2026-09-10 18:00:00');

        expect($signature)->toBe('past7days~1')
            ->and(Period::pastDays(7)->previous()->cacheSignature())->toBe($signature)
            ->and(Period::subHours(2)->previous()->previous()->cacheSignature())->toBe('sub2hours~2')
            ->and(Period::pastDays(7, 'Australia/Sydney')->previous()->cacheSignature())->toBe('past7days~1@Australia/Sydney');
    });

    it('writes a shifted relative period as its bounds', function (): void {
        expect(Period::pastDays(7)->previous()->getRouteKey())->toBe('2026-08-27..2026-09-03');
    });

    it('steps an absolute period back by its exact duration', function (): void {
        $previous = Period::create('2026-01-01', '2026-02-01')->previous();

        expect($previous->getStartDateTime()->format('Y-m-d H:i:s'))->toBe('2025-12-01 00:00:00')
            ->and($previous->getEndDateTime()->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
            ->and(Period::create('2026-02-01', '2026-03-01')->previous()->getRouteKey())->toBe('2026-01-04..2026-02-01');
    });

    it('keeps the microseconds of an absolute period', function (): void {
        $previous = Period::create('2026-09-10 12:00:00.250000', '2026-09-10 12:00:01.000000')->previous();

        expect($previous->getStartDateTime()->format('H:i:s.u'))->toBe('11:59:59.500000')
            ->and($previous->getEndDateTime()->format('H:i:s.u'))->toBe('12:00:00.250000');
    });

    it('steps an empty period back onto itself', function (): void {
        $previous = Period::create('2026-09-01', '2026-09-01')->previous();

        expect($previous->getRouteKey())->toBe('2026-09-01..2026-09-01');
    });

    it('does not mutate the period it steps back from', function (): void {
        $period = Period::create('2026-01-01', '2026-02-01');

        $period->previous();

        expect($period->getRouteKey())->toBe('2026-01-01..2026-02-01');
    });

    it('throws for a period without {case}', function (Period $period, string $key): void {
        expect(fn (): Period => $period->previous())
            ->toThrow(InvalidPeriod::class, "`{$key}` has no previous period.");
    })->with([
        'an end' => [Period::since('2026-01-01'), '2026-01-01..'],
        'a start' => [Period::upto('2026-02-01'), '..2026-02-01'],
        'any bound' => [new Period, '..'],
    ]);
});
