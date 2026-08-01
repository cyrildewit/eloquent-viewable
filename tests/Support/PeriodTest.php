<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Support\Period;

it('can instantiate class', function (): void {
    $period = $this->app->make(Period::class);

    expect($period)->toBeInstanceOf(Period::class);
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

test('absolute periods have no cache signature', function (): void {
    $period = Period::create(Carbon::yesterday(), Carbon::today());

    expect($period->cacheSignature())->toBeNull();
});
