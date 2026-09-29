<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\PendingView;

it('exposes the attributes used to create a view', function (): void {
    $viewedAt = Carbon::parse('2021-01-01 00:00:00');

    $pending = new PendingView(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: $viewedAt,
    );

    expect($pending->toArray())->toBe([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'visitor' => 'visitor_one',
        'collection' => 'custom',
        'viewed_at' => $viewedAt,
    ]);
});

it('can be serialized so it survives the queue', function (): void {
    $pending = new PendingView(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::parse('2021-01-01 00:00:00'),
    );

    $restored = unserialize(serialize($pending));

    expect($restored)->toEqual($pending);
});
