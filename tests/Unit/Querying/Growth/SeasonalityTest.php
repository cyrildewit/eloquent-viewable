<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Support\Carbon;

/** @return list<string> */
function windows(Seasonality $seasonality, Period $period, int $samples): array
{
    return array_map(
        fn (Period $reference): string => "{$reference->getStartDateTime()?->format('Y-m-d H:i')}..{$reference->getEndDateTime()?->format('Y-m-d H:i')}",
        $seasonality->references($period, $samples),
    );
}

it('steps back by whole weeks, the closest first', function (): void {
    expect(windows(Seasonality::Week, Period::create('2026-10-08 11:00', '2026-10-08 12:00'), 3))->toBe([
        '2026-10-01 11:00..2026-10-01 12:00',
        '2026-09-24 11:00..2026-09-24 12:00',
        '2026-09-17 11:00..2026-09-17 12:00',
    ]);
});

it('steps back by whole days', function (): void {
    expect(windows(Seasonality::Day, Period::create('2026-10-08 11:00', '2026-10-08 12:00'), 2))->toBe([
        '2026-10-07 11:00..2026-10-07 12:00',
        '2026-10-06 11:00..2026-10-06 12:00',
    ]);
});

it('runs a period without an end until now', function (): void {
    Carbon::setTestNow('2026-10-08 12:30:00');

    expect(windows(Seasonality::Day, Period::since('2026-10-08 11:30'), 1))->toBe(['2026-10-07 11:30..2026-10-07 12:30']);

    Carbon::setTestNow();
});

it('allows a window as long as the season', function (): void {
    expect(windows(Seasonality::Day, Period::create('2026-10-07', '2026-10-08'), 1))->toBe(['2026-10-06 00:00..2026-10-07 00:00']);
});

it('refuses a window longer than the season', function (): void {
    Seasonality::Day->references(Period::create('2026-10-06', '2026-10-08'), 1);
})->throws(InvalidBaseline::class, 'The period is longer than a day, so the windows it is compared with would overlap.');

it('refuses a period without a start', function (): void {
    Seasonality::Week->references(Period::upto('2026-10-08'), 1);
})->throws(InvalidBaseline::class, 'A baseline needs a period with a start.');

it('refuses fewer than one sample', function (): void {
    Seasonality::Week->references(Period::create('2026-10-08 11:00', '2026-10-08 12:00'), 0);
})->throws(InvalidBaseline::class, 'A baseline needs at least one sample, 0 given.');
