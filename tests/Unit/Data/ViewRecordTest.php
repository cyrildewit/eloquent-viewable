<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

it('exposes the attributes used to create a view', function (): void {
    $viewedAt = Carbon::parse('2021-01-01 00:00:00');

    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: $viewedAt,
    );

    expect($record->toArray())->toBe([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'visitor' => 'visitor_one',
        'collection' => 'custom',
        'viewed_at' => $viewedAt,
    ]);
});

it('can be serialized so it survives the queue', function (): void {
    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::parse('2021-01-01 00:00:00'),
    );

    $restored = unserialize(serialize($record));

    expect($restored)->toEqual($record);
});

it('flattens to a payload of scalars', function (): void {
    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::parse('2021-01-01 12:30:00', 'Europe/Amsterdam'),
    );

    expect($record->toPayload())->toBe([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'visitor' => 'visitor_one',
        'collection' => null,
        'viewed_at' => '2021-01-01T12:30:00+01:00',
    ]);
});

it('round-trips through its payload', function (): void {
    $record = new ViewRecord(
        viewableId: 'uuid-one',
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: Carbon::parse('2021-01-01 12:30:00', 'Europe/Amsterdam'),
    );

    $restored = ViewRecord::fromPayload($record->toPayload());

    expect($restored->viewableId)->toBe('uuid-one')
        ->and($restored->viewableType)->toBe('posts')
        ->and($restored->visitor)->toBe('visitor_one')
        ->and($restored->collection)->toBe('custom')
        ->and($restored->viewedAt->equalTo($record->viewedAt))->toBeTrue()
        ->and($restored->viewedAt->format('Y-m-d H:i:s'))->toBe('2021-01-01 12:30:00');
});

it('rebuilds from a payload that was flattened to strings', function (): void {
    $restored = ViewRecord::fromPayload([
        'viewable_id' => '1',
        'viewable_type' => 'posts',
        'visitor' => '',
        'collection' => null,
        'viewed_at' => '2021-01-01T00:00:00+00:00',
    ]);

    expect($restored->viewableId)->toBe('1')
        ->and($restored->visitor)->toBe('')
        ->and($restored->collection)->toBeNull()
        ->and($restored->viewedAt->toIso8601String())->toBe('2021-01-01T00:00:00+00:00');
});

it('keeps an integer key as an integer', function (): void {
    expect(ViewRecord::fromPayload([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'visitor' => null,
        'collection' => null,
        'viewed_at' => '2021-01-01T00:00:00+00:00',
    ])->viewableId)->toBe(1);
});
