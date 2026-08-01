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
    $startDateTime = '2018-07-16';
    $endDateTime = '2018-12-23';

    $period = new Period('2018-07-16', '2018-12-23');

    expect($period->getStartDateTime())->toEqual(Carbon::parse($startDateTime))
        ->and($period->getEndDateTime())->toEqual(Carbon::parse($endDateTime));
});

it('can construct a new period instance with start datetime argument as string', function (): void {
    $startDateTime = '2018-07-16';

    $period = new Period('2018-07-16');

    expect($period->getStartDateTime())->toEqual(Carbon::parse($startDateTime))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

it('can construct a new period instance with end datetime argument as string', function (): void {
    $endDateTime = '2018-07-16';

    $period = new Period(null, $endDateTime);

    expect($period->getStartDateTime())->not->toBeInstanceOf(CarbonInterface::class)
        ->and($period->getEndDateTime())->toEqual(Carbon::parse($endDateTime));
});

it('will throw an exception if the start date time comes after the end date time', function (): void {
    $startDateTime = Carbon::create(2018);
    $endDateTime = Carbon::create(2017);

    expect(fn (): Period => new Period($startDateTime, $endDateTime))->toThrow(InvalidPeriod::class);
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

test('static past days can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::pastDays(5);

    expect($period->getStartDateTime())->toEqual(Carbon::today()->subDays(5))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static past weeks can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::pastWeeks(2);

    expect($period->getStartDateTime())->toEqual(Carbon::today()->subWeeks(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static past months can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::pastMonths(2);

    expect($period->getStartDateTime())->toEqual(Carbon::today()->subMonths(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static past years can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::pastYears(2);

    expect($period->getStartDateTime())->toEqual(Carbon::today()->subYears(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub throws exception when sub type method is not callable', function (): void {
    Carbon::setTestNow(Carbon::now());

    expect(fn (): Period => Period::sub(Carbon::now(), 'keepDreaming', Period::SUB_SECONDS, 2))
        ->toThrow(Exception::class);
});

test('static sub seconds can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subSeconds(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subSeconds(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub minutes can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subMinutes(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subMinutes(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub hours can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subHours(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subHours(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub days can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subDays(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subDays(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub weeks can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subWeeks(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subWeeks(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub months can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subMonths(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subMonths(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub years can construct a new period instance', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::subYears(2);

    expect($period->getStartDateTime())->toEqual(Carbon::now()->subYears(2))
        ->and($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);
});

test('static sub will throw an exception if subtype method is not callable', function (): void {
    expect(fn (): Period => Period::sub(Carbon::now(), 'wrongMethod', Period::SUB_YEARS, 1))
        ->toThrow(Exception::class);
});

test('set start date time can set a new start date time', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::create();

    expect($period->getStartDateTime())->not->toBeInstanceOf(CarbonInterface::class);

    $period->setStartDateTime(Carbon::now());

    expect($period->getStartDateTime())->toEqual(Carbon::now());
});

test('set end date time can set a new start date time', function (): void {
    Carbon::setTestNow(Carbon::now());

    $period = Period::create();

    expect($period->getEndDateTime())->not->toBeInstanceOf(CarbonInterface::class);

    $period->setEndDateTime(Carbon::now());

    expect($period->getEndDateTime())->toEqual(Carbon::now());
});

test('has fixed date times can determine if datetimes are fixed', function (): void {
    $period = Period::pastDays(3);

    expect($period->hasFixedDateTimes())->toBeFalse();
});

test('get sub type returns sub type', function (): void {
    $period = Period::pastDays(3);

    expect($period->getSubType())->toBe(Period::PAST_DAYS);
});

test('get sub value returns sub type', function (): void {
    $period = Period::pastDays(3);

    expect($period->getSubValue())->toBe(3);
});

test('get sub type returns null when the period is not created from a sub type', function (): void {
    $period = Period::create(Carbon::yesterday(), Carbon::today());

    expect($period->getSubType())->toBeNull();
});

test('get sub value returns null when the period is not created from a sub type', function (): void {
    $period = Period::create(Carbon::yesterday(), Carbon::today());

    expect($period->getSubValue())->toBeNull();
});
