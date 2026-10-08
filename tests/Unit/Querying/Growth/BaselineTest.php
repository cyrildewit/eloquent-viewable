<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;

it('reads the mean and deviation of the references', function (): void {
    expect(new Baseline(30, [10, 20, 30]))
        ->mean->toBe(20.0)
        ->stddev->toEqualWithDelta(8.165, 0.001)
        ->zScore()->toEqualWithDelta(1.2247, 0.0001)
        ->ratio()->toBe(1.5);
});

it('floors the deviation at the noise of a count that size', function (): void {
    expect(new Baseline(60, [5, 5, 5, 5]))->zScore()->toEqualWithDelta(55 / sqrt(5), 0.0001)
        ->and(new Baseline(3, [0, 0]))->zScore()->toBe(3.0);
});

it('keeps the ratio finite when there was nothing to grow from', function (): void {
    expect(new Baseline(12, [0]))->ratio()->toBe(12.0)
        ->and(new Baseline(1, [0, 1]))->ratio()->toBe(1.0);
});

it('is negative below the mean', function (): void {
    expect(new Baseline(2, [40, 40]))->zScore()->toEqualWithDelta(-38 / sqrt(40), 0.0001);
});

it('serialises to an array and JSON', function (): void {
    $baseline = new Baseline(6, [4, 4]);

    expect($baseline->toArray())->toBe([
        'current' => 6,
        'references' => [4, 4],
        'mean' => 4.0,
        'stddev' => 0.0,
        'z_score' => 1.0,
        'ratio' => 1.5,
    ])
        ->and(json_encode($baseline))->toBe('{"current":6,"references":[4,4],"mean":4,"stddev":0,"z_score":1,"ratio":1.5}');
});
