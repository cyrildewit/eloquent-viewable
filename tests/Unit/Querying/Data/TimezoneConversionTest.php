<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Querying\Data\OffsetSegment;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Support\Timezone;

function conversion(string $from, string $to, string $start, string $end): TimezoneConversion
{
    return new TimezoneConversion(
        new Timezone($from),
        new Timezone($to),
        Carbon::parse($start, 'UTC'),
        Carbon::parse($end, 'UTC'),
    );
}

/** @return list<array{?string, int}> */
function segments(TimezoneConversion $conversion): array
{
    return array_map(fn (OffsetSegment $segment): array => [$segment->startsAt, $segment->offset], $conversion->segments());
}

it('is immutable', function (): void {
    $reflection = new ReflectionClass(TimezoneConversion::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('exposes its zones and bounds', function (): void {
    $conversion = conversion('UTC', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-09-02 00:00:00');

    expect($conversion->from->getName())->toBe('UTC')
        ->and($conversion->to->getName())->toBe('Australia/Sydney')
        ->and($conversion->start->format('Y-m-d'))->toBe('2026-09-01')
        ->and($conversion->end->format('Y-m-d'))->toBe('2026-09-02');
});

describe('segments', function (): void {
    it('is a single unbounded segment when neither zone transitions', function (): void {
        // Sydney is on standard time all winter; nothing moves between June and August.
        expect(segments(conversion('UTC', 'Australia/Sydney', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe([[null, 36000]]);
    });

    it('keeps a half-hour zone exact', function (): void {
        expect(segments(conversion('UTC', 'Asia/Kolkata', '2026-06-01 00:00:00', '2026-09-01 00:00:00')))
            ->toBe([[null, 19800]]);
    });

    it('cuts a segment where the target zone transitions', function (): void {
        // Sydney springs forward at 2026-10-04 02:00 AEST, which is 2026-10-03 16:00 UTC.
        expect(segments(conversion('UTC', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe([[null, 36000], ['2026-10-03 16:00:00', 39600]]);
    });

    it('cuts a segment where the storage zone transitions, on its own wall clock', function (): void {
        // Amsterdam falls back at 2026-10-25 01:00 UTC, when 03:00 CEST becomes
        // 02:00 CET. The threshold is the later reading of that wall clock.
        expect(segments(conversion('Europe/Amsterdam', 'UTC', '2026-10-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe([[null, -7200], ['2026-10-25 02:00:00', -3600]]);
    });

    it('cuts on the transitions of both zones', function (): void {
        expect(segments(conversion('Europe/Amsterdam', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-11-01 00:00:00')))
            ->toBe([
                [null, 28800],
                ['2026-10-03 18:00:00', 32400],
                ['2026-10-25 02:00:00', 36000],
            ]);
    });

    it('merges transitions that leave the difference unchanged', function (): void {
        // Amsterdam and London both move at 01:00 UTC on the same days.
        expect(segments(conversion('Europe/London', 'Europe/Amsterdam', '2026-01-01 00:00:00', '2027-01-01 00:00:00')))
            ->toBe([[null, 3600]]);
    });

    it('ignores a transition at the very start of the range', function (): void {
        expect(segments(conversion('UTC', 'Australia/Sydney', '2026-10-03 16:00:00', '2026-11-01 00:00:00')))
            ->toBe([[null, 39600]]);
    });

    it('ignores transitions after the range', function (): void {
        expect(segments(conversion('UTC', 'Australia/Sydney', '2026-09-01 00:00:00', '2026-10-01 00:00:00')))
            ->toBe([[null, 36000]]);
    });
});

describe('noop', function (): void {
    it('is a noop between a zone and itself', function (): void {
        expect(conversion('Europe/Amsterdam', 'Europe/Amsterdam', '2026-01-01 00:00:00', '2027-01-01 00:00:00')->isNoop())->toBeTrue();
    });

    it('is a noop between two zones that keep the same clock', function (): void {
        expect(conversion('Europe/London', 'Europe/Lisbon', '2026-01-01 00:00:00', '2027-01-01 00:00:00')->isNoop())->toBeTrue();
    });

    it('is not a noop when the clocks differ anywhere in the range', function (): void {
        // Europe/Dublin keeps GMT in winter and IST in summer, London the same, so
        // they coincide; Africa/Casablanca does not.
        expect(conversion('UTC', 'Europe/London', '2026-01-01 00:00:00', '2026-02-01 00:00:00')->isNoop())->toBeTrue()
            ->and(conversion('UTC', 'Europe/London', '2026-01-01 00:00:00', '2026-07-01 00:00:00')->isNoop())->toBeFalse();
    });
});
