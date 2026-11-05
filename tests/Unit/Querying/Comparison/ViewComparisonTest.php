<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Contracts\Support\Arrayable;

function comparison(int $current, int $previous): ViewComparison
{
    return ViewComparison::between($current, $previous, Period::create('2026-09-08', '2026-09-15'), Period::create('2026-09-01', '2026-09-08'));
}

it('is immutable', function (): void {
    $reflection = new ReflectionClass(ViewComparison::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('exposes both counts and periods', function (): void {
    $current = Period::create('2026-09-08', '2026-09-15');
    $previous = Period::create('2026-09-01', '2026-09-08');

    $comparison = ViewComparison::between(340, 290, $current, $previous);

    expect($comparison->current)->toBe(340)
        ->and($comparison->previous)->toBe(290)
        ->and($comparison->currentPeriod)->toBe($current)
        ->and($comparison->previousPeriod)->toBe($previous);
});

it('computes the delta and the percentage of {current} against {previous}', function (int $current, int $previous, int $delta, ?float $percent): void {
    $comparison = comparison($current, $previous);

    expect($comparison->delta)->toBe($delta)
        ->and($comparison->percent)->toBe($percent);
})->with([
    'growth' => [340, 290, 50, 17.2],
    'decline' => [290, 340, -50, -14.7],
    'rounded up' => [3, 2, 1, 50.0],
    'one decimal' => [1001, 1000, 1, 0.1],
    'half up' => [10_005, 10_000, 5, 0.1],
    'doubled' => [20, 10, 10, 100.0],
    'flat' => [5, 5, 0, 0.0],
    'gone' => [0, 5, -5, -100.0],
    'from nothing' => [5, 0, 5, null],
    'nothing at all' => [0, 0, 0, null],
]);

it('is arrayable and serialises to JSON', function (): void {
    $comparison = comparison(340, 290);

    expect($comparison)->toBeInstanceOf(Arrayable::class)
        ->and($comparison->toArray())->toBe(['current' => 340, 'previous' => 290, 'delta' => 50, 'percent' => 17.2])
        ->and(json_encode($comparison))->toBe('{"current":340,"previous":290,"delta":50,"percent":17.2}')
        ->and(json_encode(comparison(5, 0)))->toBe('{"current":5,"previous":0,"delta":5,"percent":null}');
});
