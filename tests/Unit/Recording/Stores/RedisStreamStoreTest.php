<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Recording\Contracts\BufferedViewStore;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Streams\FakeStreamClient;

function redisRecord(int|string $viewableId, string $viewableType = Post::class): ViewRecord
{
    return new ViewRecord($viewableId, $viewableType, 'visitor_one', null, Carbon::parse('2021-01-01 12:30:00', 'UTC'));
}

beforeEach(function (): void {
    $this->client = new FakeStreamClient;
    $this->landing = new ArrayStore;
    $this->store = new RedisStreamStore(new ViewStream($this->client, 'views', 'flushers'), $this->landing);
});

it('is a buffered store', function (): void {
    expect($this->store)->toBeInstanceOf(BufferedViewStore::class);
});

it('buffers a record in the stream instead of the landing store', function (): void {
    $this->store->store(redisRecord(1));

    expect($this->client->entries)->toBe(['1-0' => StreamEntry::encode(redisRecord(1))])
        ->and($this->landing->records())->toBe([]);
});

it('buffers a batch in one call', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2)]);

    expect($this->client->batches)->toHaveCount(1)
        ->and($this->client->ids())->toBe(['1-0', '2-0']);
});

it('lands a batch through the landing store and drops it from the stream', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2), redisRecord(3)]);

    expect($this->store->flush(2))->toBe(2)
        ->and($this->landing->records())->toHaveCount(2)
        ->and($this->landing->records()[0]->viewableId)->toBe('1')
        ->and($this->landing->records()[1]->viewableId)->toBe('2')
        ->and($this->client->acknowledged)->toBe(['1-0', '2-0'])
        ->and($this->client->ids())->toBe(['3-0'])
        ->and($this->client->groups)->toBe(['flushers']);
});

it('lands nothing when the stream is empty', function (): void {
    $landing = Mockery::mock(ViewStore::class);
    $landing->expects('storeMany')->never();

    $store = new RedisStreamStore(new ViewStream($this->client, 'views', 'flushers'), $landing);

    expect($store->flush())->toBe(0)
        ->and($this->client->acknowledged)->toBe([]);
});

it('lands the entries another flusher abandoned before new ones', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2)]);
    $this->client->abandoned = ['2-0'];

    expect($this->store->flush(2))->toBe(2)
        ->and($this->landing->records()[0]->viewableId)->toBe('2')
        ->and($this->landing->records()[1]->viewableId)->toBe('1');
});

it('acknowledges an entry deleted while pending without landing it', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2)]);
    $this->client->abandoned = ['9-0'];

    expect($this->store->flush(3))->toBe(2)
        ->and($this->landing->records())->toHaveCount(2)
        ->and($this->client->acknowledged)->toBe(['9-0', '1-0', '2-0']);
});

it('skips the landing store when a batch holds only deleted entries', function (): void {
    $landing = Mockery::mock(ViewStore::class);
    $landing->expects('storeMany')->never();

    $this->client->abandoned = ['9-0'];
    $store = new RedisStreamStore(new ViewStream($this->client, 'views', 'flushers'), $landing);

    expect($store->flush())->toBe(0)
        ->and($this->client->acknowledged)->toBe(['9-0']);
});

it('forgets the buffered and the landed views of a viewable', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2), redisRecord(1, Apartment::class)]);
    $this->store->flush(1);
    $this->store->storeMany([redisRecord(1)]);

    $this->store->forget(new Post(['id' => 1]));

    expect($this->client->ids())->toBe(['2-0', '3-0'])
        ->and($this->landing->records())->toBe([]);
});

it('forgets every buffered view of a type for a viewable without a key', function (): void {
    $this->store->storeMany([redisRecord(1), redisRecord(2), redisRecord(1, Apartment::class)]);

    $this->store->forget(new Post);

    expect($this->client->ids())->toBe(['3-0']);
});

it('leaves an entry without fields out of the scan', function (): void {
    $this->client->entries['0-0'] = [];
    $this->store->store(redisRecord(1));

    $this->store->forget(new Post(['id' => 1]));

    expect($this->client->ids())->toBe(['0-0']);
});
