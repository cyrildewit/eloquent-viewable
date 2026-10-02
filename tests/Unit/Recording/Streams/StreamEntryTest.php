<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RedisStreamFailed;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;

it('encodes a record as string fields and leaves the empty columns out', function (): void {
    $record = new ViewRecord(7, 'posts', null, null, Carbon::parse('2021-01-01 12:30:00', 'UTC'));

    expect(StreamEntry::encode($record))->toBe([
        'viewable_id' => '7',
        'viewable_type' => 'posts',
        'viewed_at' => '2021-01-01T12:30:00+00:00',
    ]);
});

it('encodes the visitor and the collection when set', function (): void {
    $record = new ViewRecord('uuid-1', 'posts', 'visitor_one', 'sidebar', Carbon::parse('2021-01-01 12:30:00', 'UTC'));

    expect(StreamEntry::encode($record))->toBe([
        'viewable_id' => 'uuid-1',
        'viewable_type' => 'posts',
        'visitor' => 'visitor_one',
        'collection' => 'sidebar',
        'viewed_at' => '2021-01-01T12:30:00+00:00',
    ]);
});

it('rebuilds the record from its fields', function (): void {
    $entry = new StreamEntry('1-0', [
        'viewable_id' => '7',
        'viewable_type' => 'posts',
        'visitor' => 'visitor_one',
        'collection' => 'sidebar',
        'viewed_at' => '2021-01-01T12:30:00+00:00',
    ]);

    $record = $entry->record();

    expect($record->viewableId)->toBe('7')
        ->and($record->viewableType)->toBe('posts')
        ->and($record->visitor)->toBe('visitor_one')
        ->and($record->collection)->toBe('sidebar')
        ->and($record->viewedAt->equalTo(Carbon::parse('2021-01-01 12:30:00', 'UTC')))->toBeTrue();
});

it('fills the columns that were left out back in as null', function (): void {
    $record = new StreamEntry('1-0', [
        'viewable_id' => '7',
        'viewable_type' => 'posts',
        'viewed_at' => '2021-01-01T12:30:00+00:00',
    ])->record();

    expect($record->visitor)->toBeNull()
        ->and($record->collection)->toBeNull();
});

it('refuses to rebuild a record from an entry that misses a field', function (string $missing): void {
    $fields = ['viewable_id' => '7', 'viewable_type' => 'posts', 'viewed_at' => '2021-01-01T12:30:00+00:00'];
    unset($fields[$missing]);

    expect(fn (): ViewRecord => new StreamEntry('1-0', $fields)->record())
        ->toThrow(RedisStreamFailed::class, 'The entry `1-0` of the Redis view stream is missing the fields of a view record.');
})->with(['viewable_id', 'viewable_type', 'viewed_at']);

it('coerces the fields a client hands back to strings', function (): void {
    expect(StreamEntry::of('1-0', ['viewable_id' => 7, 'viewable_type' => 'posts', 'nested' => ['ignored'], 'flag' => true]))
        ->id->toBe('1-0')
        ->fields->toBe(['viewable_id' => '7', 'viewable_type' => 'posts', 'flag' => '1']);
});

it('is empty when the client hands back no fields', function (mixed $fields): void {
    $entry = StreamEntry::of('1-0', $fields);

    expect($entry->isEmpty())->toBeTrue()
        ->and($entry->fields)->toBeEmpty();
})->with([
    'null' => [null],
    'false' => [false],
    'empty array' => [[]],
]);

it('is not empty when it has fields', function (): void {
    expect(new StreamEntry('1-0', ['viewable_id' => '7'])->isEmpty())->toBeFalse();
});
