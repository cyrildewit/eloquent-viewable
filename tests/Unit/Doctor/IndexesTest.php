<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Support\Indexes;

it('is covered by an index that starts with the columns', function (): void {
    $indexes = new Indexes([['viewable_type', 'viewable_id', 'viewed_at', 'visitor']]);

    expect($indexes->cover(['viewable_type', 'viewable_id', 'viewed_at']))->toBeTrue()
        ->and($indexes->cover(['viewable_type', 'viewable_id', 'viewed_at', 'visitor']))->toBeTrue();
});

it('is not covered by an index that holds the columns in another order or further in', function (): void {
    $indexes = new Indexes([['viewable_type', 'viewable_id', 'viewed_at']]);

    expect($indexes->cover(['viewed_at']))->toBeFalse()
        ->and($indexes->cover(['viewable_id', 'viewable_type']))->toBeFalse()
        ->and($indexes->cover(['viewable_type', 'viewable_id', 'viewed_at', 'visitor']))->toBeFalse();
});

it('is not covered without indexes', function (): void {
    expect(new Indexes([])->cover(['viewed_at']))->toBeFalse();
});
