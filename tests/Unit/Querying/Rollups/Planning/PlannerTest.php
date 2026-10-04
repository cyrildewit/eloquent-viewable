<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Plan;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Planner;
use CyrildeWit\EloquentViewable\Querying\Rollups\Planning\Segment;
use CyrildeWit\EloquentViewable\Querying\Rollups\Snapshot;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;

function at(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'UTC');
}

/**
 * A day and a month tier folded from 2025-01-01, the day tier up to
 * 2026-03-31 and the month tier up to 2026-03-01.
 *
 * @param  array<string, string>  $since
 */
function folded(?string $anonymised = null, ?string $pruned = null, array $since = []): Snapshot
{
    return new Snapshot(
        ['day' => at('2026-03-31'), 'month' => at('2026-03-01')],
        ['day' => at($since['day'] ?? '2025-01-01'), 'month' => at($since['month'] ?? '2025-01-01')],
        $anonymised === null ? null : at($anonymised),
        $pruned === null ? null : at($pruned),
    );
}

/** @return list<string> */
function describePlan(Plan $plan): array
{
    return array_map(static fn (Segment $segment): string => sprintf(
        '%s %s..%s%s',
        $segment->tier->value ?? 'raw',
        $segment->start?->format('Y-m-d H:i') ?? '',
        $segment->end?->format('Y-m-d H:i') ?? '',
        $segment->exact ? '' : ' ~',
    ), $plan->segments);
}

function plan(Snapshot $state, ?string $start, ?string $end, bool $unique = false, ?Closure $align = null, array $tiers = [Tier::Month, Tier::Day]): Plan
{
    return new Planner(new DateTimeZone('UTC'))->plan($state, $tiers, $start === null ? null : at($start), $end === null ? null : at($end), $unique, $align);
}

it('reads everything raw while no tier is folded', function (): void {
    $plan = plan(new Snapshot, '2026-01-01', null);

    expect(describePlan($plan))->toBe(['raw 2026-01-01 00:00..'])
        ->and($plan->isRawOnly())->toBeTrue();
});

it('reads all time from the coarsest tier, its edges from finer ones and the rest raw', function (): void {
    expect(describePlan(plan(folded(), null, null)))->toBe([
        'raw ..2025-01-01 00:00',
        'month 2025-01-01 00:00..2026-03-01 00:00',
        'day 2026-03-01 00:00..2026-03-31 00:00',
        'raw 2026-03-31 00:00..',
    ]);
});

it('reads nothing raw before the first bucket any tier folded', function (): void {
    $state = new Snapshot(
        ['day' => at('2026-03-31'), 'month' => at('2026-03-01')],
        ['day' => at('2025-01-10'), 'month' => at('2025-01-01')],
        origin: at('2025-01-01'),
    );

    expect(describePlan(plan($state, null, null)))->toBe([
        'month 2025-01-01 00:00..2026-03-01 00:00',
        'day 2026-03-01 00:00..2026-03-31 00:00',
        'raw 2026-03-31 00:00..',
    ])
        ->and(describePlan(plan($state, '2024-12-15 10:00', '2025-01-10')))->toBe([
            'raw 2025-01-01 00:00..2025-01-10 00:00',
        ]);
});

it('reads whole months from the month tier and the days around them from the day tier', function (): void {
    $plan = plan(folded(), '2026-01-15', '2026-03-20');

    expect(describePlan($plan))->toBe([
        'day 2026-01-15 00:00..2026-02-01 00:00',
        'month 2026-02-01 00:00..2026-03-01 00:00',
        'day 2026-03-01 00:00..2026-03-20 00:00',
    ])
        ->and($plan->isExact())->toBeTrue()
        ->and($plan->raw())->toBeEmpty()
        ->and($plan->parts(new DateTimeZone('UTC')))->toBe(17 + 1 + 19);
});

it('reads a recent period raw', function (): void {
    expect(describePlan(plan(folded(), '2026-03-31 06:00', null)))->toBe(['raw 2026-03-31 06:00..']);
});

it('reads an edge raw while the views table still holds it', function (): void {
    expect(describePlan(plan(folded(pruned: '2026-01-01'), '2026-01-15 10:00', '2026-02-01')))->toBe([
        'raw 2026-01-15 10:00..2026-01-16 00:00',
        'day 2026-01-16 00:00..2026-02-01 00:00',
    ]);
});

it('counts the buckets starting inside an edge the views table no longer holds', function (): void {
    $plan = plan(folded(pruned: '2026-02-01'), '2026-01-15 10:00', '2026-02-01');

    expect(describePlan($plan))->toBe([
        'day 2026-01-15 10:00..2026-01-16 00:00 ~',
        'day 2026-01-16 00:00..2026-02-01 00:00',
    ])
        ->and($plan->isExact())->toBeFalse();
});

it('counts an edge in the month tier once the day tier expired it', function (): void {
    expect(describePlan(plan(folded(pruned: '2026-01-01', since: ['day' => '2026-01-01']), '2025-06-15', '2026-03-20')))->toBe([
        'month 2025-06-15 00:00..2025-07-01 00:00 ~',
        'month 2025-07-01 00:00..2026-03-01 00:00',
        'day 2026-03-01 00:00..2026-03-20 00:00',
    ]);
});

it('drops what no tier and no view holds any more', function (): void {
    expect(describePlan(plan(folded(pruned: '2026-01-01', since: ['day' => '2026-01-01', 'month' => '2025-06-01']), '2025-01-01', '2025-07-01')))->toBe([
        'month 2025-06-01 00:00..2025-07-01 00:00',
    ]);
});

it('reads unique visitors raw while nothing is anonymised or pruned', function (): void {
    expect(describePlan(plan(folded(), '2026-01-15', null, unique: true)))->toBe(['raw 2026-01-15 00:00..']);
});

it('reads unique visitors raw back to where the views table is exact', function (): void {
    $plan = plan(folded(anonymised: '2026-03-10'), '2026-01-15', null, unique: true);

    expect(describePlan($plan))->toBe([
        'day 2026-01-15 00:00..2026-02-01 00:00',
        'month 2026-02-01 00:00..2026-03-01 00:00',
        'day 2026-03-01 00:00..2026-03-10 00:00',
        'raw 2026-03-10 00:00..',
    ])
        ->and($plan->boundaries())->toHaveCount(3);
});

it('hands over to raw on the edge of a series bucket', function (): void {
    $month = static fn (CarbonImmutable $moment): CarbonImmutable => $moment->startOfMonth();

    expect(describePlan(plan(folded(), '2026-01-01', null, align: $month)))->toBe([
        'month 2026-01-01 00:00..2026-03-01 00:00',
        'raw 2026-03-01 00:00..',
    ])
        ->and(describePlan(plan(folded(pruned: '2026-03-15'), '2026-01-01', null, align: $month)))->toBe([
            'month 2026-01-01 00:00..2026-03-01 00:00',
            'day 2026-03-01 00:00..2026-03-31 00:00',
            'raw 2026-03-31 00:00..',
        ]);
});

it('reads only from the tiers it is given', function (): void {
    expect(describePlan(plan(folded(), '2026-01-15', '2026-03-20', tiers: [Tier::Day])))->toBe([
        'day 2026-01-15 00:00..2026-03-20 00:00',
    ]);
});

it('counts every bucket of a segment without a start as more than one part', function (): void {
    $state = new Snapshot(['month' => at('2026-03-01')]);

    expect(describePlan(plan($state, null, '2026-02-01', tiers: [Tier::Month])))->toBe(['month ..2026-02-01 00:00'])
        ->and(plan($state, null, '2026-02-01', tiers: [Tier::Month])->parts(new DateTimeZone('UTC')))->toBe(PHP_INT_MAX);
});

it('turns a raw segment into a period', function (): void {
    expect(new Segment(null, at('2026-01-01'), null)->period()?->getStartDateTime()?->toDateString())->toBe('2026-01-01')
        ->and(new Segment(null, null, null)->period())->toBeNull();
});
