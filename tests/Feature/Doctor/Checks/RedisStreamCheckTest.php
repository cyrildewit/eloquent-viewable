<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Doctor\Checks\RedisStreamCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Data\RedisServer;
use CyrildeWit\EloquentViewable\Recording\Stores\NullStore;
use CyrildeWit\EloquentViewable\Recording\Stores\RedisStreamStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use CyrildeWit\EloquentViewable\Recording\Streams\ViewStream;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Streams\FakeStreamClient;
use Illuminate\Support\Carbon;

/** @return list<array{Status, string}> */
function streamFindings(): array
{
    $findings = iterator_to_array(app()->make(RedisStreamCheck::class)->run(), preserve_keys: false);

    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], $findings);
}

/**
 * @param  list<string>  $ids
 * @param  list<string>  $abandoned
 */
function bufferedStream(array $ids = [], array $abandoned = [], ?RedisServer $server = null): void
{
    $client = new FakeStreamClient;
    $client->server = $server;

    foreach ($ids as $id) {
        $client->entries[$id] = ['viewable_id' => '1'];
    }

    $client->abandoned = $abandoned;

    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->make(StoreManager::class)->extend('redis', fn (): ViewStore => new RedisStreamStore(
        new ViewStream($client, 'views', 'flushers'),
        new NullStore,
    ));
}

function idAgo(int $seconds): string
{
    return CarbonImmutable::now()->subSeconds($seconds)->getTimestampMs().'-0';
}

beforeEach(function (): void {
    Carbon::setTestNow('2024-05-01 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
    app()->make(StoreManager::class)->forgetDrivers();
});

it('skips when the redis store is not in use', function (): void {
    expect(streamFindings())->toBe([
        [Status::Skipped, 'The `redis` store driver is not in use.'],
    ]);
});

it('skips a redis driver of your own', function (): void {
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->make(StoreManager::class)->extend('redis', fn (): ViewStore => new NullStore);

    expect(streamFindings())->toBe([
        [Status::Skipped, 'The `redis` store driver is one of your own.'],
    ]);
});

it('passes an empty stream', function (): void {
    bufferedStream();

    expect(streamFindings())->toBe([
        [Status::Pass, 'The stream is empty: every buffered view has landed in the views table.'],
    ]);
});

it('passes views that have waited a short while', function (): void {
    bufferedStream([idAgo(90), idAgo(30)]);

    expect(streamFindings())->toBe([
        [Status::Pass, '2 views wait in the stream, the oldest for 1 minute.'],
    ]);
});

it('warns about views that have waited too long', function (): void {
    bufferedStream([idAgo(RedisStreamCheck::StaleAfter + 60)]);

    expect(streamFindings())->toBe([
        [Status::Warning, '1 view waits in the stream, the oldest for 6 minutes, so `views:flush` does not run or does not keep up.'],
    ]);
});

it('warns about views a flush took and never acknowledged', function (): void {
    bufferedStream([idAgo(30), idAgo(20)], abandoned: [idAgo(30), idAgo(20)]);

    expect(streamFindings()[1])->toBe([Status::Warning, '2 views were taken by a flush that never acknowledged them, so it failed halfway through.']);
});

it('names the server the stream is kept on', function (RedisServer $server, string $summary): void {
    bufferedStream(server: $server);

    expect(streamFindings()[0])->toBe([Status::Pass, $summary]);
})->with([
    'Redis' => [new RedisServer('Redis', '7.4.11', '7.4.11'), 'The stream is kept on Redis 7.4.11.'],
    'Valkey' => [new RedisServer('Valkey', '8.1.11', '7.2.4'), 'The stream is kept on Valkey 8.1.11.'],
]);

it('fails a server older than Redis 7', function (): void {
    bufferedStream(server: new RedisServer('Redis', '6.2.14', '6.2.14'));

    expect(streamFindings()[0])->toBe([Status::Failure, 'The stream is kept on Redis 6.2.14, and the `redis` store driver needs Redis 7 or newer.']);
});

it('passes views that landed before it read the oldest one', function (): void {
    $client = Mockery::mock(StreamClient::class);
    $client->allows('range')->andReturn([]);
    $client->allows('length')->andReturn(1);
    $client->allows('pending')->andReturn(0);
    $client->allows('stalled')->andReturn(0);
    $client->allows('server')->andReturn(null);

    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->make(StoreManager::class)->extend('redis', fn (): ViewStore => new RedisStreamStore(
        new ViewStream($client, 'views', 'flushers'),
        new NullStore,
    ));

    expect(streamFindings())->toBe([
        [Status::Pass, '1 view waits in the stream.'],
    ]);
});

it('reads the stream through both clients', function (string $client): void {
    if ($client === 'phpredis' && ! extension_loaded('redis')) {
        $this->markTestSkipped('The phpredis extension is not installed.');
    }

    config()->set('database.redis.client', $client);
    config()->set('eloquent-viewable.recording.store.driver', 'redis');

    app()->forgetInstance('redis');
    app()->make(StoreManager::class)->forgetDrivers();

    [$server, $stream] = streamFindings();

    expect($server[0])->toBe(Status::Pass)
        ->and($server[1])->toMatch('/^The stream is kept on (Redis|Valkey) \d+\.\d+\.\d+\.$/')
        ->and($stream[0])->toBe(Status::Pass);
})->with(['phpredis', 'predis']);
