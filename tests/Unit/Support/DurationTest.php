<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\PeriodInterval;

it('parses the shorthand {0}', function (string $shorthand, PeriodInterval $interval, int $value): void {
    $duration = Duration::tryParse($shorthand);

    expect($duration)
        ->interval->toBe($interval)
        ->value->toBe($value)
        ->shorthand()->toBe($shorthand);
})->with([
    ['90s', PeriodInterval::Seconds, 90],
    ['30min', PeriodInterval::Minutes, 30],
    ['12h', PeriodInterval::Hours, 12],
    ['30d', PeriodInterval::Days, 30],
    ['4w', PeriodInterval::Weeks, 4],
    ['6m', PeriodInterval::Months, 6],
    ['2y', PeriodInterval::Years, 2],
]);

it('parses nothing from {0}', function (string $duration): void {
    expect(Duration::tryParse($duration))->toBeNull();
})->with(['', 'd', '0d', '-3d', '07d', '3 d', '3D', '3x', '2026-01-01..']);

it('counts back from the moment it is handed', function (): void {
    $now = Carbon::parse('2026-03-31 15:30:00');

    expect(Duration::tryParse('30d')?->before($now)->toDateTimeString())->toBe('2026-03-01 15:30:00')
        ->and(Duration::tryParse('12h')?->before($now)->toDateTimeString())->toBe('2026-03-31 03:30:00')
        ->and($now->toDateTimeString())->toBe('2026-03-31 15:30:00');
});

it('compares lengths measured back from the same moment', function (string $longer, string $shorter): void {
    $now = Carbon::parse('2026-03-31 12:00:00');

    expect(Duration::tryParse($longer)?->isLongerThan(Duration::tryParse($shorter) ?? throw new LogicException, $now))->toBeTrue()
        ->and(Duration::tryParse($shorter)?->isLongerThan(Duration::tryParse($longer) ?? throw new LogicException, $now))->toBeFalse();
})->with([
    ['30d', '4w'],
    ['1y', '11m'],
    ['25h', '1d'],
]);

it('is not longer than an equal duration', function (): void {
    $now = Carbon::parse('2026-03-31 12:00:00');

    expect(new Duration(PeriodInterval::Weeks, 1)->isLongerThan(new Duration(PeriodInterval::Days, 7), $now))->toBeFalse();
});
