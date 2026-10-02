<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\PeriodAnchor;
use CyrildeWit\EloquentViewable\Support\PeriodInterval;

it('subtracts {interval} without mutating the date', function (PeriodInterval $interval, string $expected): void {
    $dateTime = Carbon::parse('2026-01-10 12:00:00');

    $result = $interval->subtract($dateTime, 3);

    expect($result->format('Y-m-d H:i:s'))->toBe($expected)
        ->and($dateTime->format('Y-m-d H:i:s'))->toBe('2026-01-10 12:00:00');
})->with([
    'seconds' => [PeriodInterval::Seconds, '2026-01-10 11:59:57'],
    'minutes' => [PeriodInterval::Minutes, '2026-01-10 11:57:00'],
    'hours' => [PeriodInterval::Hours, '2026-01-10 09:00:00'],
    'days' => [PeriodInterval::Days, '2026-01-07 12:00:00'],
    'weeks' => [PeriodInterval::Weeks, '2025-12-20 12:00:00'],
    'months' => [PeriodInterval::Months, '2025-10-10 12:00:00'],
    'years' => [PeriodInterval::Years, '2023-01-10 12:00:00'],
]);

it('has a shorthand per {interval} that reads back', function (PeriodInterval $interval, string $shorthand, PeriodAnchor $anchor): void {
    expect($interval->shorthand())->toBe($shorthand)
        ->and(PeriodInterval::fromShorthand($shorthand))->toBe($interval)
        ->and($interval->anchor())->toBe($anchor);
})->with([
    'seconds' => [PeriodInterval::Seconds, 's', PeriodAnchor::Sub],
    'minutes' => [PeriodInterval::Minutes, 'min', PeriodAnchor::Sub],
    'hours' => [PeriodInterval::Hours, 'h', PeriodAnchor::Sub],
    'days' => [PeriodInterval::Days, 'd', PeriodAnchor::Past],
    'weeks' => [PeriodInterval::Weeks, 'w', PeriodAnchor::Past],
    'months' => [PeriodInterval::Months, 'm', PeriodAnchor::Past],
    'years' => [PeriodInterval::Years, 'y', PeriodAnchor::Past],
]);

it('reads no interval from an unknown shorthand', function (): void {
    expect(PeriodInterval::fromShorthand('x'))->toBeNull()
        ->and(PeriodInterval::fromShorthand('D'))->toBeNull();
});
