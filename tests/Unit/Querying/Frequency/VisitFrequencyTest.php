<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Frequency\VisitFrequency;

it('gathers the visitors at and above the cap into one bucket', function (): void {
    $frequency = VisitFrequency::fold([1 => 820, 2 => 140, 3 => 40, 7 => 20]);

    expect($frequency->toArray())->toBe([1 => 820, 2 => 140, '3+' => 60]);
});

it('fills the buckets without visitors with zero', function (): void {
    expect(VisitFrequency::fold([4 => 2], 5)->toArray())->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 2, '5+' => 0])
        ->and(VisitFrequency::fold([])->toArray())->toBe([1 => 0, 2 => 0, '3+' => 0]);
});

it('splits the visitors into new and returning', function (): void {
    $frequency = VisitFrequency::fold([1 => 820, 2 => 140, 5 => 60]);

    expect($frequency->new())->toBe(820)
        ->and($frequency->returning())->toBe(200)
        ->and($frequency->total())->toBe(1020)
        ->and($frequency->returningShare())->toBe(0.196);
});

it('has no share without visitors', function (): void {
    $frequency = VisitFrequency::fold([]);

    expect($frequency->new())->toBe(0)
        ->and($frequency->returning())->toBe(0)
        ->and($frequency->returningShare())->toBeNull();
});

it('serialises to its buckets', function (): void {
    expect(json_encode(VisitFrequency::fold([1 => 3, 2 => 1], 2)))->toBe('{"1":3,"2+":1}');
});
