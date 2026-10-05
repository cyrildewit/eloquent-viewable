<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\ExponentialDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\Window;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use CyrildeWit\EloquentViewable\Querying\Ranking\Step;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-04 13:20:00', 'UTC'));
});

function decay(?Period $period = null, ?DecayCurve $curve = null, ?Granularity $step = null, int $maxSteps = 500, string $timezone = 'UTC'): Decay
{
    return Decay::for(new ViewsQuery($period), $curve ?? new ExponentialDecay(CarbonInterval::day()), $step, $maxSteps, new Timezone($timezone));
}

/** @return list<array{string, int}> */
function stepsOf(Decay $decay): array
{
    return array_map(static fn (Step $step): array => [$step->start->format('Y-m-d H:i:s'), $step->weight], $decay->steps());
}

it('starts the steps at the floor of now and walks back an hour at a time', function (): void {
    $steps = stepsOf(decay(step: Granularity::Hour));

    expect($steps[0])->toBe(['2026-10-04 13:00:00', 1_000_000])
        ->and($steps[1])->toBe(['2026-10-04 12:00:00', 971_532])
        ->and($steps[24])->toBe(['2026-10-03 13:00:00', 500_000])
        ->and($steps)->toHaveCount(193)
        ->and($steps[192])->toBe(['2026-09-26 13:00:00', 3_906]);
});

it('walks back a day at a time', function (): void {
    expect(array_slice(stepsOf(decay(step: Granularity::Day)), 0, 3))->toBe([
        ['2026-10-04 00:00:00', 1_000_000],
        ['2026-10-03 00:00:00', 500_000],
        ['2026-10-02 00:00:00', 250_000],
    ]);
});

it('floors day steps on the clock of the zone it is given', function (): void {
    expect(array_slice(stepsOf(decay(step: Granularity::Day, timezone: 'Europe/Amsterdam')), 0, 2))->toBe([
        ['2026-10-03 22:00:00', 1_000_000],
        ['2026-10-02 22:00:00', 500_000],
    ])->and(decay(step: Granularity::Day, timezone: 'Europe/Amsterdam')->steps()[0]->start->getTimezone()->getName())->toBe('UTC');
});

it('measures ages from the end of a past period', function (): void {
    $steps = stepsOf(decay(Period::create('2026-09-01', '2026-10-01'), step: Granularity::Day));

    expect($steps)->toHaveCount(21)
        ->and($steps[0])->toBe(['2026-09-30 00:00:00', 1_000_000])
        ->and($steps[20])->toBe(['2026-09-10 00:00:00', 1]);
});

it('weighs per hour while the window fits under the cap', function (): void {
    expect(decay(Period::create('2026-09-25', '2026-10-01'))->step())->toBe(Granularity::Hour)
        ->and(decay(Period::create('2026-09-25', '2026-10-01'))->steps())->toHaveCount(144);
});

it('weighs per day once hours would exceed the cap', function (): void {
    expect(decay(Period::create('2026-09-01', '2026-10-01'))->step())->toBe(Granularity::Day)
        ->and(decay(Period::create('2026-09-01', '2026-10-01'), maxSteps: 720)->step())->toBe(Granularity::Hour);
});

it('refuses a step that exceeds the cap', function (): void {
    decay(step: Granularity::Hour, maxSteps: 100);
})->throws(InvalidDecay::class, 'The trending window would be weighed in 193 steps, more than the maximum of 100.');

it('refuses a window too long even for days', function (): void {
    decay(Period::create('2020-01-01', '2026-10-01'));
})->throws(InvalidDecay::class, 'more than the maximum of 500');

it('refuses a weight of {0}', function (float $weight): void {
    $curve = Mockery::mock(DecayCurve::class);
    $curve->allows('horizon')->andReturn(CarbonInterval::day());
    $curve->allows('weight')->andReturn($weight);

    decay(curve: $curve);
})->throws(InvalidDecay::class, 'returned a weight of')->with([1.5, -0.1, NAN]);

it('leaves out the steps whose weight rounds to zero', function (): void {
    expect(stepsOf(decay(Period::create('2026-09-26', '2026-10-01'), new LinearDecay(CarbonInterval::days(2)), Granularity::Day)))->toBe([
        ['2026-09-30 00:00:00', 1_000_000],
        ['2026-09-29 00:00:00', 500_000],
    ]);
});

it('reads a period without a start back to the horizon of the curve', function (): void {
    $query = decay(curve: new Window(CarbonInterval::days(2)), step: Granularity::Day)->narrow(new ViewsQuery);

    expect($query->period?->getStartDateTime()?->format('Y-m-d H:i:s'))->toBe('2026-10-03 00:00:00')
        ->and($query->period?->getEndDateTime())->toBeNull();
});

it('reads the period it is given', function (): void {
    $query = new ViewsQuery(Period::create('2026-09-26 08:30', '2026-10-01'), 'amp', true);
    $narrowed = decay($query->period, step: Granularity::Day)->narrow($query);

    expect($narrowed->period?->getStartDateTime()?->format('Y-m-d H:i:s'))->toBe('2026-09-26 08:30:00')
        ->and($narrowed->period?->getEndDateTime()?->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
        ->and($narrowed->collection)->toBe('amp')
        ->and($narrowed->unique)->toBeTrue();
});

it('has no steps over a period that has not started', function (): void {
    $decay = decay(Period::since('2026-10-05'));

    expect($decay->steps())->toBeEmpty()
        ->and($decay->narrow(new ViewsQuery)->period?->getStartDateTime()?->format('Y-m-d H:i:s'))->toBe('2026-10-04 13:20:00');
});

it('is identified without now', function (): void {
    $identity = decay()->identity();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-04 15:45:00', 'UTC'));

    expect(decay()->identity())->toBe($identity)
        ->and(decay(step: Granularity::Day)->identity())->not->toBe($identity)
        ->and(decay(curve: new ExponentialDecay(CarbonInterval::week()), maxSteps: 2_000)->identity())->not->toBe($identity)
        ->and(decay(Period::create('2026-09-01', '2026-10-01'))->identity())->not->toBe($identity);
});
