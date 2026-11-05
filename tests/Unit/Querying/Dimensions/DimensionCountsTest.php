<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;

it('orders the values by count, then by value', function (): void {
    $counts = DimensionCounts::from(['Bing' => 2, 'Google' => 5, 'Direct' => 2, '2024' => 1]);

    expect($counts->all())->toBe(['Google' => 5, 'Bing' => 2, 'Direct' => 2, '2024' => 1])
        ->and($counts->values())->toBe(['Google', 'Bing', 'Direct', '2024']);
});

it('adds the values, none and other up to the total of views', function (): void {
    $counts = DimensionCounts::from(['Google' => 5, 'Bing' => 2], none: 3, other: 1);

    expect($counts->total())->toBe(11)
        ->and($counts->none())->toBe(3)
        ->and($counts->other())->toBe(1)
        ->and($counts->get('Google'))->toBe(5)
        ->and($counts->get('2024'))->toBe(0)
        ->and($counts->get('Missing'))->toBe(0);
});

it('keeps a total it is given, as unique visitors need', function (): void {
    expect(DimensionCounts::from(['Google' => 5, 'Bing' => 2], total: 6)->total())->toBe(6);
});

it('gives the share of a value, rounded, and none without views', function (): void {
    $counts = DimensionCounts::from(['Google' => 2, 'Bing' => 1]);

    expect($counts->share('Google'))->toBe(0.667)
        ->and($counts->share('Missing'))->toBe(0.0)
        ->and(DimensionCounts::from([])->share('Google'))->toBeNull();
});

it('folds the values past a limit into other', function (): void {
    $counts = DimensionCounts::from(['Google' => 5, 'Bing' => 2, 'Direct' => 1], none: 4)->limit(1);

    expect($counts->all())->toBe(['Google' => 5])
        ->and($counts->other())->toBe(3)
        ->and($counts->none())->toBe(4)
        ->and($counts->total())->toBe(12)
        ->and(DimensionCounts::from(['Google' => 5])->limit(null)->all())->toBe(['Google' => 5]);
});

it('adds another read of the same dimension', function (): void {
    $counts = DimensionCounts::from(['Google' => 5, 'Bing' => 1], none: 1)
        ->add(DimensionCounts::from(['Bing' => 6, 'Direct' => 1], none: 2, other: 3));

    expect($counts->all())->toBe(['Bing' => 7, 'Google' => 5, 'Direct' => 1])
        ->and($counts->none())->toBe(3)
        ->and($counts->other())->toBe(3)
        ->and($counts->total())->toBe(19);
});

it('turns into an array and JSON, and back', function (): void {
    $counts = DimensionCounts::from(['Google' => 5], none: 1, other: 2);
    $array = ['values' => ['Google' => 5], 'none' => 1, 'other' => 2, 'total' => 8];

    expect($counts->toArray())->toBe($array)
        ->and($counts->jsonSerialize())->toBe($array)
        ->and(json_encode($counts))->toBe('{"values":{"Google":5},"none":1,"other":2,"total":8}')
        ->and(DimensionCounts::fromArray($array)->toArray())->toBe($array);
});
