<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Recording\Stores\DatabaseStore;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\ClientFactory;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

const REDIS_STREAM = 'eloquent-viewable:views';

const REDIS_GROUP = 'eloquent-viewable';

function useRedisStore(string $client): RedisStreamStore
{
    if ($client === 'phpredis' && ! extension_loaded('redis')) {
        test()->markTestSkipped('The phpredis extension is not installed.');
    }

    config()->set('database.redis.client', $client);
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->forgetInstance('redis');
    app()->make(StoreManager::class)->forgetDrivers();

    redisConnection()->command('del', [REDIS_STREAM]);

    /** @var RedisStreamStore $store */
    $store = app()->make(ViewStore::class);

    return $store;
}

function redisConnection(): Connection
{
    return app()->make(RedisFactory::class)->connection();
}

function streamLength(): int
{
    return (int) redisConnection()->command('xlen', [REDIS_STREAM]);
}

dataset('redis clients', ['phpredis', 'predis']);

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is the store the redis driver builds', function (string $client): void {
    $store = useRedisStore($client);

    expect($store)->toBeInstanceOf(RedisStreamStore::class)
        ->and($this->app->make(ViewStore::class))->toBe($store);
})->with('redis clients');

it('buffers a recorded view until it is flushed', function (string $client): void {
    $store = useRedisStore($client);

    Carbon::setTestNow('2021-01-01 12:30:00');

    expect(views($this->post)->collection('sidebar')->record())->toBeTrue()
        ->and(View::count())->toBe(0)
        ->and(streamLength())->toBe(1)
        ->and($store->flush())->toBe(1)
        ->and(streamLength())->toBe(0);

    $view = View::sole();

    expect($view->viewable_id)->toBe($this->post->getKey())
        ->and($view->viewable_type)->toBe($this->post->getMorphClass())
        ->and($view->visitor)->toBeString()
        ->and($view->collection)->toBe('sidebar')
        ->and(Carbon::parse($view->viewed_at)->equalTo(Carbon::parse('2021-01-01 12:30:00')))->toBeTrue()
        ->and($this->post)->toHaveViewsCount(1);
})->with('redis clients');

it('lands the viewer and the context of a buffered view', function (string $client): void {
    $store = useRedisStore($client);
    $user = User::factory()->create();

    views($this->post)->viewedBy($user)->context(['source' => 'newsletter', 'tags' => ['a', 'b']])->record();
    views($this->post)->record();

    expect($store->flush())->toBe(2);

    $credited = View::whereNotNull('viewer_id')->sole();
    $guest = View::whereNull('viewer_id')->sole();

    expect($credited->viewer->is($user))->toBeTrue()
        // MySQL stores a JSON object with its keys sorted, so the order is not asserted.
        ->and($credited->context)->toEqual(['source' => 'newsletter', 'tags' => ['a', 'b']])
        ->and($guest->viewer_type)->toBeNull()
        ->and($guest->context)->toBeNull();
})->with('redis clients');

it('dispatches ViewRecorded once the stream has accepted the view', function (string $client): void {
    useRedisStore($client);
    Event::fake([ViewRecorded::class]);

    views($this->post)->record();

    Event::assertDispatched(ViewRecorded::class, fn (ViewRecorded $event): bool => $event->record->viewableId === $this->post->getKey());
})->with('redis clients');

it('buffers a batch and lands it in one insert', function (string $client): void {
    $store = useRedisStore($client);
    $other = Post::factory()->create();
    $viewedAt = Carbon::parse('2021-01-01 12:30:00');

    $store->storeMany([
        new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_one', null, $viewedAt),
        new ViewRecord($this->post->getKey(), $this->post->getMorphClass(), 'visitor_two', 'custom', $viewedAt),
        new ViewRecord($other->getKey(), $other->getMorphClass(), null, null, $viewedAt->copy()->addMinute()),
    ]);

    expect(streamLength())->toBe(3);

    DB::enableQueryLog();

    expect($store->flush())->toBe(3)
        ->and(DB::getQueryLog())->toHaveCount(1)
        ->and($this->post)->toHaveViewsCount(2)
        ->and($other)->toHaveViewsCount(1)
        ->and(View::where('collection', 'custom')->sole()->visitor)->toBe('visitor_two')
        ->and(View::where('visitor', null)->sole()->viewable_id)->toBe($other->getKey());
})->with('redis clients');

it('lands at most a batch per flush', function (string $client): void {
    $store = useRedisStore($client);

    for ($i = 0; $i < 5; $i++) {
        views($this->post)->record();
    }

    expect($store->flush(2))->toBe(2)
        ->and($store->flush(2))->toBe(2)
        ->and($store->flush(2))->toBe(1)
        ->and($store->flush(2))->toBe(0)
        ->and($this->post)->toHaveViewsCount(5)
        ->and(streamLength())->toBe(0);
})->with('redis clients');

it('lands the batch of a flusher that crashed before acknowledging', function (string $client): void {
    useRedisStore($client);

    views($this->post)->record();
    views($this->post)->record();

    $stream = new ViewStream(ClientFactory::make(redisConnection()), REDIS_STREAM, REDIS_GROUP, 'crashed');

    // Taken, never acknowledged: the entries stay pending for the group.
    expect($stream->take(10))->toHaveCount(2)
        ->and(View::count())->toBe(0);

    $patient = new RedisStreamStore(new ViewStream(ClientFactory::make(redisConnection()), REDIS_STREAM, REDIS_GROUP), $this->app->make(DatabaseStore::class));
    $impatient = new RedisStreamStore(new ViewStream(ClientFactory::make(redisConnection()), REDIS_STREAM, REDIS_GROUP, claimAfter: 0), $this->app->make(DatabaseStore::class));

    expect($patient->flush())->toBe(0)
        ->and($impatient->flush())->toBe(2)
        ->and($this->post)->toHaveViewsCount(2)
        ->and(streamLength())->toBe(0);
})->with('redis clients');

it('lets go of the views forgotten while a crashed flusher held them', function (string $client): void {
    $store = useRedisStore($client);

    views($this->post)->record();
    views($this->post)->record();

    expect(new ViewStream(ClientFactory::make(redisConnection()), REDIS_STREAM, REDIS_GROUP, 'crashed')->take(10))->toHaveCount(2);

    $store->forget($this->post);

    $impatient = new RedisStreamStore(new ViewStream(ClientFactory::make(redisConnection()), REDIS_STREAM, REDIS_GROUP, claimAfter: 0), $this->app->make(DatabaseStore::class));

    expect($impatient->flush())->toBe(0)
        ->and($impatient->flush())->toBe(0)
        ->and($this->post)->toHaveViewsCount(0)
        ->and(redisConnection()->command('xpending', [REDIS_STREAM, REDIS_GROUP])[0])->toBe(0);
})->with('redis clients');

it('leaves the consumer group in place between flushes', function (string $client): void {
    $store = useRedisStore($client);

    views($this->post)->record();
    expect($store->flush())->toBe(1);

    $this->app->make(StoreManager::class)->forgetDrivers();
    views($this->post)->record();

    expect($this->app->make(ViewStore::class)->flush())->toBe(1)
        ->and($this->post)->toHaveViewsCount(2);
})->with('redis clients');

it('forgets the buffered and the landed views of a viewable', function (string $client): void {
    $store = useRedisStore($client);
    $other = Post::factory()->create();

    views($this->post)->record();
    views($other)->record();
    $store->flush();

    views($this->post)->collection('sidebar')->record();
    views($other)->record();

    $store->forget($this->post);

    expect(View::count())->toBe(1)
        ->and($other)->toHaveViewsCount(1)
        ->and(streamLength())->toBe(1);

    $store->flush();

    expect($this->post)->toHaveViewsCount(0)
        ->and($other)->toHaveViewsCount(2);
})->with('redis clients');

it('lands the buffered views a filter keeps ahead of the flush', function (string $client): void {
    $store = useRedisStore($client);
    $other = Post::factory()->create();

    views($this->post)->record();
    views($other)->record();
    views($this->post)->record();

    expect($store->land(fn (ViewRecord $record): bool => $record->belongsTo($this->post)))->toBe(2)
        ->and($this->post)->toHaveViewsCount(2)
        ->and($other)->toHaveViewsCount(0)
        ->and(streamLength())->toBe(1)
        ->and($store->land(fn (ViewRecord $record): bool => false))->toBe(0)
        ->and($store->flush())->toBe(1)
        ->and($this->post)->toHaveViewsCount(2);
})->with('redis clients');

it('forgets the buffered views when the viewable is deleted', function (string $client): void {
    useRedisStore($client);

    views($this->post)->record();

    $this->post->delete();

    expect(streamLength())->toBe(0);
})->with('redis clients');
