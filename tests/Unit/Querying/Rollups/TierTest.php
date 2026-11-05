<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Granularity;

it('lists the tiers coarse to fine', function (): void {
    expect(Tier::coarseToFine())->toBe([Tier::Year, Tier::Month, Tier::Day, Tier::Hour])
        ->and(Tier::Month->isCoarserThan(Tier::Day))->toBeTrue()
        ->and(Tier::Day->isCoarserThan(Tier::Month))->toBeFalse()
        ->and(Tier::Day->granularity())->toBe(Granularity::Day);
});

it('fits a {1} series from the {0} tier: {2}', function (Tier $tier, Granularity $granularity, bool $fits): void {
    expect($tier->fits($granularity))->toBe($fits);
})->with([
    [Tier::Hour, Granularity::Hour, true],
    [Tier::Day, Granularity::Hour, false],
    [Tier::Day, Granularity::Week, true],
    [Tier::Month, Granularity::Week, false],
    [Tier::Month, Granularity::Month, true],
    [Tier::Year, Granularity::Month, false],
    [Tier::Year, Granularity::Year, true],
    [Tier::Hour, Granularity::Year, true],
]);

it('finds bucket edges on the clock of the zone and hands them back on the clock it was given', function (): void {
    $zone = new DateTimeZone('Europe/Amsterdam');
    $moment = CarbonImmutable::parse('2026-03-14 23:30:00', 'UTC');

    expect(Tier::Day->floor($moment, $zone)->toDateTimeString())->toBe('2026-03-14 23:00:00')
        ->and(Tier::Day->next(Tier::Day->floor($moment, $zone), $zone)->toDateTimeString())->toBe('2026-03-15 23:00:00')
        ->and(Tier::Day->floor($moment, $zone)->getTimezone()->getName())->toBe('UTC')
        ->and(Tier::Month->floor($moment, $zone)->toDateTimeString())->toBe('2026-02-28 23:00:00');
});

it('keeps a day whole across a change to daylight saving time', function (): void {
    $zone = new DateTimeZone('Europe/Amsterdam');
    $day = Tier::Day->floor(CarbonImmutable::parse('2026-03-29 12:00:00', 'UTC'), $zone);

    expect($day->toDateTimeString())->toBe('2026-03-28 23:00:00')
        ->and(Tier::Day->next($day, $zone)->toDateTimeString())->toBe('2026-03-29 22:00:00');
});

it('rounds up to the next edge unless it is on one', function (): void {
    $zone = new DateTimeZone('UTC');

    expect(Tier::Day->ceil(CarbonImmutable::parse('2026-01-15 10:00:00', 'UTC'), $zone)->toDateTimeString())->toBe('2026-01-16 00:00:00')
        ->and(Tier::Day->ceil(CarbonImmutable::parse('2026-01-15 00:00:00', 'UTC'), $zone)->toDateTimeString())->toBe('2026-01-15 00:00:00');
});

it('counts the buckets between two edges', function (): void {
    $zone = new DateTimeZone('UTC');

    expect(Tier::Month->countBetween(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-01'), $zone))->toBe(3)
        ->and(Tier::Day->countBetween(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-02'), $zone))->toBe(1);
});
