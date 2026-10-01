<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

it('is immutable', function (): void {
    $reflection = new ReflectionClass(ViewsQuery::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('defaults to no period, no collection and non-unique', function (): void {
    $query = new ViewsQuery;

    expect($query->period)->toBeNull()
        ->and($query->collection)->toBeNull()
        ->and($query->unique)->toBeFalse();
});

it('exposes what it was constructed with', function (): void {
    $period = Period::pastDays(3);

    $query = new ViewsQuery($period, 'custom', true);

    expect($query->period)->toBe($period)
        ->and($query->collection)->toBe('custom')
        ->and($query->unique)->toBeTrue();
});
