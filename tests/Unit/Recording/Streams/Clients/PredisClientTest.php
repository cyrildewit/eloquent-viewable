<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Streams\Clients\PredisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\StreamEntry;
use Illuminate\Redis\Connections\PredisConnection;
use Predis\Pipeline\Pipeline;
use Predis\Response\ServerException;

beforeEach(function (): void {
    $this->connection = Mockery::mock(PredisConnection::class);
    $this->client = new PredisClient($this->connection);
});

it('appends with the fields before the id', function (): void {
    $this->connection->expects('command')->with('xadd', ['views', ['viewable_id' => '1'], '*'])->andReturn('1-0');

    $this->client->add('views', ['viewable_id' => '1']);
});

it('appends a batch through a pipeline', function (): void {
    $pipe = Mockery::mock(Pipeline::class);
    $pipe->expects('xadd')->with('views', ['viewable_id' => '1'], '*');
    $pipe->expects('xadd')->with('views', ['viewable_id' => '2'], '*');

    $this->connection->expects('pipeline')->withArgs(function (callable $callback) use ($pipe): bool {
        $callback($pipe);

        return true;
    })->andReturn([]);

    $this->client->addMany('views', [['viewable_id' => '1'], ['viewable_id' => '2']]);
});

it('creates the group from the start of the stream', function (): void {
    $this->connection->expects('command')->with('xgroup', ['CREATE', 'views', 'flushers', '0', true])->andReturn('OK');

    $this->client->createGroup('views', 'flushers');
});

it('leaves a group that already exists alone', function (): void {
    $this->connection->expects('command')->andThrow(new ServerException('BUSYGROUP Consumer Group name already exists'));

    $this->client->createGroup('views', 'flushers');
});

it('reports any other error while creating the group', function (): void {
    $this->connection->expects('command')->andThrow(new ServerException('WRONGTYPE Operation against a key holding the wrong kind of value'));

    expect(fn () => $this->client->createGroup('views', 'flushers'))
        ->toThrow(ServerException::class, 'WRONGTYPE Operation against a key holding the wrong kind of value');
});

it('claims the entries that have been pending for long enough', function (): void {
    $this->connection->expects('command')->with('xpending', ['views', 'flushers', 60_000, '-', '+', 10])->andReturn([
        ['1-0', 'crashed', 75_000, 1],
        ['2-0', 'crashed', 80_000, 2],
        'junk',
    ]);
    $this->connection->expects('command')->with('xclaim', ['views', 'flushers', 'worker', 60_000, ['1-0', '2-0']])->andReturn([
        '1-0' => ['viewable_id' => '1', 'viewable_type' => 'posts'],
        '2-0' => [],
    ]);

    expect($this->client->claim('views', 'flushers', 'worker', 60_000, 10))->toEqual([
        new StreamEntry('1-0', ['viewable_id' => '1', 'viewable_type' => 'posts']),
        new StreamEntry('2-0', []),
    ]);
});

it('claims nothing when nothing has been pending for long enough', function (mixed $pending): void {
    $this->connection->expects('command')->with('xpending', Mockery::any())->andReturn($pending);
    $this->connection->expects('command')->with('xclaim', Mockery::any())->never();

    expect($this->client->claim('views', 'flushers', 'worker', 60_000, 10))->toBe([]);
})->with([
    'empty list' => [[]],
    'null' => [null],
]);

it('claims nothing from an unexpected claim reply', function (): void {
    $this->connection->expects('command')->with('xpending', Mockery::any())->andReturn([['1-0', 'crashed', 75_000, 1]]);
    $this->connection->expects('command')->with('xclaim', Mockery::any())->andReturn(null);

    expect($this->client->claim('views', 'flushers', 'worker', 60_000, 10))->toBe([]);
});

it('reads new entries from the raw reply', function (): void {
    $this->connection->expects('command')->with('xreadgroup', ['flushers', 'worker', 10, null, false, 'views', '>'])->andReturn([
        ['laravel_database_views', [
            ['1-0', ['viewable_id', '1']],
            ['2-0', ['viewable_id', '2']],
        ]],
    ]);

    expect($this->client->read('views', 'flushers', 'worker', 10))->toEqual([
        new StreamEntry('1-0', ['viewable_id' => '1']),
        new StreamEntry('2-0', ['viewable_id' => '2']),
    ]);
});

it('reads an entry deleted while pending as an entry without fields', function (): void {
    $this->connection->expects('command')->andReturn([
        ['views', [['1-0', null], ['2-0', ['viewable_id', '2']], 'junk']],
    ]);

    expect($this->client->read('views', 'flushers', 'worker', 10))->toEqual([
        new StreamEntry('1-0', []),
        new StreamEntry('2-0', ['viewable_id' => '2']),
    ]);
});

it('reads nothing when there is nothing new', function (mixed $reply): void {
    $this->connection->expects('command')->andReturn($reply);

    expect($this->client->read('views', 'flushers', 'worker', 10))->toBe([]);
})->with([
    'null' => [null],
    'empty array' => [[]],
    'stream without entries' => [['views']],
    'stream with an unexpected entry list' => [[['views', 'junk']]],
]);

it('reads a range from the start of the stream', function (): void {
    $this->connection->expects('command')->with('xrange', ['views', '-', '+', 100])->andReturn(['1-0' => ['viewable_id' => '1']]);

    expect($this->client->range('views', null, 100))->toEqual([new StreamEntry('1-0', ['viewable_id' => '1'])]);
});

it('reads a range after an id, excluding it', function (): void {
    $this->connection->expects('command')->with('xrange', ['views', '(1-0', '+', 100])->andReturn([]);

    expect($this->client->range('views', '1-0', 100))->toBe([]);
});

it('reads no range from an unexpected reply', function (): void {
    $this->connection->expects('command')->andReturn(null);

    expect($this->client->range('views', null, 100))->toBe([]);
});

it('acknowledges ids as separate arguments', function (): void {
    $this->connection->expects('command')->with('xack', ['views', 'flushers', '1-0', '2-0'])->andReturn(2);

    $this->client->acknowledge('views', 'flushers', ['1-0', '2-0']);
});

it('deletes ids as separate arguments', function (): void {
    $this->connection->expects('command')->with('xdel', ['views', '1-0', '2-0'])->andReturn(2);

    $this->client->delete('views', ['1-0', '2-0']);
});
