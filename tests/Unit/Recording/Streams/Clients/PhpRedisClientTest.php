<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Exceptions\RedisStreamFailed;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\PhpRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use Illuminate\Redis\Connections\PhpRedisConnection;

beforeEach(function (): void {
    $this->connection = Mockery::mock(PhpRedisConnection::class);
    $this->client = new PhpRedisClient($this->connection);
});

it('appends with the id before the fields', function (): void {
    $this->connection->expects('command')->with('xadd', ['views', '*', ['viewable_id' => '1']])->andReturn('1-0');

    $this->client->add('views', ['viewable_id' => '1']);
});

it('appends a batch through a pipeline', function (): void {
    $pipe = Mockery::mock(Redis::class);
    $pipe->expects('xadd')->with('views', '*', ['viewable_id' => '1']);
    $pipe->expects('xadd')->with('views', '*', ['viewable_id' => '2']);

    $this->connection->expects('pipeline')->withArgs(function (callable $callback) use ($pipe): bool {
        $callback($pipe);

        return true;
    })->andReturn([]);

    $this->client->addMany('views', [['viewable_id' => '1'], ['viewable_id' => '2']]);
});

it('creates the group from the start of the stream', function (): void {
    $this->connection->expects('command')->with('xgroup', ['CREATE', 'views', 'flushers', '0', true])->andReturn(true);

    $this->client->createGroup('views', 'flushers');
});

it('leaves a group that already exists alone', function (): void {
    $redis = Mockery::mock(Redis::class);
    $redis->expects('getLastError')->andReturn('BUSYGROUP Consumer Group name already exists');
    $redis->expects('clearLastError');

    $this->connection->expects('command')->with('xgroup', ['CREATE', 'views', 'flushers', '0', true])->andReturn(false);
    $this->connection->expects('client')->andReturn($redis);

    $this->client->createGroup('views', 'flushers');
});

it('reports any other error while creating the group', function (): void {
    $redis = Mockery::mock(Redis::class);
    $redis->expects('getLastError')->andReturn('WRONGTYPE Operation against a key holding the wrong kind of value');
    $redis->expects('clearLastError');

    $this->connection->expects('command')->andReturn(false);
    $this->connection->expects('client')->andReturn($redis);

    expect(fn () => $this->client->createGroup('views', 'flushers'))
        ->toThrow(RedisStreamFailed::class, 'Could not create the consumer group `flushers` on the Redis stream `views`: WRONGTYPE Operation against a key holding the wrong kind of value');
});

it('reports a failure without a message when the client cannot tell', function (): void {
    $this->connection->expects('command')->andReturn(false);
    $this->connection->expects('client')->andReturn(new stdClass);

    expect(fn () => $this->client->createGroup('views', 'flushers'))
        ->toThrow(RedisStreamFailed::class, 'Could not create the consumer group `flushers` on the Redis stream `views`: ');
});

it('claims abandoned entries', function (): void {
    $this->connection->expects('command')->with('xautoclaim', ['views', 'flushers', 'worker', 60_000, '0-0', 10])->andReturn([
        '0-0',
        ['1-0' => ['viewable_id' => '1'], '2-0' => false],
        ['3-0'],
    ]);

    expect($this->client->claim('views', 'flushers', 'worker', 60_000, 10))->toEqual([
        new StreamEntry('1-0', ['viewable_id' => '1']),
        new StreamEntry('2-0', []),
    ]);
});

it('claims nothing from an error reply', function (): void {
    $this->connection->expects('command')->andReturn(false);

    expect($this->client->claim('views', 'flushers', 'worker', 60_000, 10))->toBe([]);
});

it('reads new entries from the one stream whatever key prefix it carries', function (): void {
    $this->connection->expects('command')->with('xreadgroup', ['flushers', 'worker', ['views' => '>'], 10])->andReturn([
        'laravel_database_views' => ['1-0' => ['viewable_id' => '1'], '2-0' => ['viewable_id' => '2']],
    ]);

    expect($this->client->read('views', 'flushers', 'worker', 10))->toEqual([
        new StreamEntry('1-0', ['viewable_id' => '1']),
        new StreamEntry('2-0', ['viewable_id' => '2']),
    ]);
});

it('reads nothing when there is nothing new', function (mixed $reply): void {
    $this->connection->expects('command')->andReturn($reply);

    expect($this->client->read('views', 'flushers', 'worker', 10))->toBe([]);
})->with([
    'empty array' => [[]],
    'false' => [false],
]);

it('reads a range from the start of the stream', function (): void {
    $this->connection->expects('command')->with('xrange', ['views', '-', '+', 100])->andReturn(['1-0' => ['viewable_id' => '1']]);

    expect($this->client->range('views', null, 100))->toEqual([new StreamEntry('1-0', ['viewable_id' => '1'])]);
});

it('reads a range after an id, excluding it', function (): void {
    $this->connection->expects('command')->with('xrange', ['views', '(1-0', '+', 100])->andReturn([]);

    expect($this->client->range('views', '1-0', 100))->toBe([]);
});

it('reads no range from an error reply', function (): void {
    $this->connection->expects('command')->andReturn(false);

    expect($this->client->range('views', null, 100))->toBe([]);
});

it('acknowledges ids as one list', function (): void {
    $this->connection->expects('command')->with('xack', ['views', 'flushers', ['1-0', '2-0']])->andReturn(2);

    $this->client->acknowledge('views', 'flushers', ['1-0', '2-0']);
});

it('deletes ids as one list', function (): void {
    $this->connection->expects('command')->with('xdel', ['views', ['1-0', '2-0']])->andReturn(2);

    $this->client->delete('views', ['1-0', '2-0']);
});
