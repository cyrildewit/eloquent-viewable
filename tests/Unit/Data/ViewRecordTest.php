<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;

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
        'viewer_type' => null,
        'viewer_id' => null,
        'visitor' => 'visitor_one',
        'collection' => 'custom',
        'context' => null,
        'viewed_at' => $viewedAt,
    ]);
});

it('defaults to a guest view without context', function (): void {
    $record = new ViewRecord(1, 'posts', null, null, Carbon::now());

    expect($record->viewerType)->toBeNull()
        ->and($record->viewerId)->toBeNull()
        ->and($record->context)->toBeNull();
});

it('encodes the viewer and the context as columns', function (): void {
    $viewedAt = Carbon::parse('2021-01-01 00:00:00');

    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: $viewedAt,
        viewerType: 'users',
        viewerId: 7,
        context: ['source' => 'newsletter', 'tags' => ['a', 'b']],
    );

    expect($record->toArray())->toBe([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'viewer_type' => 'users',
        'viewer_id' => 7,
        'visitor' => 'visitor_one',
        'collection' => null,
        'context' => '{"source":"newsletter","tags":["a","b"]}',
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
        viewerType: 'users',
        viewerId: 'uuid-seven',
        context: ['source' => 'newsletter'],
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
        viewerType: 'users',
        viewerId: 7,
        context: ['source' => 'newsletter'],
    );

    expect($record->toPayload())->toBe([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'viewer_type' => 'users',
        'viewer_id' => 7,
        'visitor' => 'visitor_one',
        'collection' => null,
        'context' => '{"source":"newsletter"}',
        'viewed_at' => '2021-01-01T12:30:00+01:00',
        'dimensions' => null,
    ]);
});

it('carries its dimensions as one key of the payload and as columns of the row', function (): void {
    $record = new ViewRecord(
        viewableId: 1,
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: null,
        viewedAt: Carbon::parse('2021-01-01 12:30:00', 'UTC'),
        dimensions: ['source' => 'Google', 'campaign' => null],
    );

    expect($record->toPayload()['dimensions'])->toBe('{"source":"Google","campaign":null}')
        ->and($record->toArray())->toMatchArray(['source' => 'Google', 'campaign' => null])
        ->and(ViewRecord::fromPayload($record->toPayload())->dimensions)->toBe(['source' => 'Google', 'campaign' => null])
        ->and(ViewRecord::fromPayload([...$record->toPayload(), 'dimensions' => ['source' => 'Bing']])->dimensions)->toBe(['source' => 'Bing']);
});

it('round-trips through its payload', function (): void {
    $record = new ViewRecord(
        viewableId: 'uuid-one',
        viewableType: 'posts',
        visitor: 'visitor_one',
        collection: 'custom',
        viewedAt: Carbon::parse('2021-01-01 12:30:00', 'Europe/Amsterdam'),
        viewerType: 'users',
        viewerId: 'uuid-seven',
        context: ['source' => 'newsletter', 'nested' => ['depth' => 2]],
    );

    $restored = ViewRecord::fromPayload($record->toPayload());

    expect($restored->viewableId)->toBe('uuid-one')
        ->and($restored->viewableType)->toBe('posts')
        ->and($restored->visitor)->toBe('visitor_one')
        ->and($restored->collection)->toBe('custom')
        ->and($restored->viewerType)->toBe('users')
        ->and($restored->viewerId)->toBe('uuid-seven')
        ->and($restored->context)->toBe(['source' => 'newsletter', 'nested' => ['depth' => 2]])
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
        'viewer_type' => 'users',
        'viewer_id' => '7',
        'context' => '{"source":"newsletter"}',
    ]);

    expect($restored->viewableId)->toBe('1')
        ->and($restored->visitor)->toBe('')
        ->and($restored->collection)->toBeNull()
        ->and($restored->viewerType)->toBe('users')
        ->and($restored->viewerId)->toBe('7')
        ->and($restored->context)->toBe(['source' => 'newsletter'])
        ->and($restored->viewedAt->toIso8601String())->toBe('2021-01-01T00:00:00+00:00');
});

it('rebuilds from a payload without the viewer and the context', function (): void {
    $restored = ViewRecord::fromPayload([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'visitor' => null,
        'collection' => null,
        'viewed_at' => '2021-01-01T00:00:00+00:00',
    ]);

    expect($restored->viewerType)->toBeNull()
        ->and($restored->viewerId)->toBeNull()
        ->and($restored->context)->toBeNull()
        ->and($restored->dimensions)->toBeEmpty();
});

it('accepts a context that was not flattened', function (): void {
    expect(ViewRecord::fromPayload([
        'viewable_id' => 1,
        'viewable_type' => 'posts',
        'viewed_at' => '2021-01-01T00:00:00+00:00',
        'context' => ['source' => 'newsletter'],
    ])->context)->toBe(['source' => 'newsletter']);
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

it('belongs to the viewable with its type and key', function (): void {
    $record = new ViewRecord(7, Post::class, 'visitor_one', null, Carbon::now());

    expect($record->belongsTo(new Post(['id' => 7])))->toBeTrue()
        ->and($record->belongsTo(new Post(['id' => 8])))->toBeFalse()
        ->and($record->belongsTo(new Apartment(['id' => 7])))->toBeFalse();
});

it('matches a string key against an integer one', function (): void {
    expect(new ViewRecord('7', Post::class, null, null, Carbon::now())->belongsTo(new Post(['id' => 7])))->toBeTrue();
});

it('belongs to every viewable of its type for a viewable without a key', function (): void {
    $record = new ViewRecord(7, Post::class, null, null, Carbon::now());

    expect($record->belongsTo(new Post))->toBeTrue()
        ->and($record->belongsTo(new Apartment))->toBeFalse();
});
