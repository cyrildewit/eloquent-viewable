<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\DimensionFilter;

it('reads the keys of a target in context', function (): void {
    expect(new DimensionFilter('plan', 'context->billing->plan', ['pro'])->keys())->toBe(['billing', 'plan'])
        ->and(new DimensionFilter('source', 'source', ['Google'])->keys())->toBeEmpty();
});

it('signs the same values the same in any order', function (): void {
    expect(new DimensionFilter('source', 'source', ['Google', 'Bing'])->signature())
        ->toBe(new DimensionFilter('source', 'source', ['Bing', 'Google'])->signature())
        ->not->toBe(new DimensionFilter('device', 'device', ['Bing', 'Google'])->signature());
});
