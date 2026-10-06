<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Precision;
use CyrildeWit\EloquentViewable\Presence\Stores\PresenceManager;
use CyrildeWit\EloquentViewable\Presence\Stores\RedisPresenceStore;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Exceptions;

function useRedisPresence(string $client, string $precision = 'exact'): RedisPresenceStore
{
    if ($client === 'phpredis' && ! extension_loaded('redis')) {
        test()->markTestSkipped('The phpredis extension is not installed.');
    }

    config()->set('database.redis.client', $client);
    config()->set('eloquent-viewable.presence.enabled', true);
    config()->set('eloquent-viewable.presence.driver', 'redis');
    config()->set('eloquent-viewable.presence.precision', $precision);

    app()->forgetInstance('redis');
    app()->make(PresenceManager::class)->forgetDrivers();

    presenceRedis()->command('flushdb');

    /** @var RedisPresenceStore $store */
    $store = app()->make(PresenceStore::class);

    return $store;
}

function presenceRedis(): Connection
{
    return app()->make(RedisFactory::class)->connection();
}

function redisSighting(string $visitor, int $key = 7, ?string $collection = null, ?Reference $viewer = null, string $type = 'post'): Sighting
{
    return new Sighting($type, $key, $visitor, Carbon::now(), $collection, $viewer);
}

/**
 * @param  list<Reference>  $references
 * @return list<string>
 */
function redisReferences(array $references): array
{
    return array_map(static fn (Reference $reference): string => $reference->encode(), $references);
}

dataset('presence clients', ['phpredis', 'predis']);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-06 12:00:30');
});

it('is the store the redis driver builds', function (string $client): void {
    expect(useRedisPresence($client))->toBeInstanceOf(RedisPresenceStore::class);
})->with('presence clients');

describe('exact', function (): void {
    it('counts the distinct visitors of every scope', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one'));
        $store->touch(redisSighting('one'));
        $store->touch(redisSighting('two', collection: 'amp'));
        $store->touch(redisSighting('three', key: 8, type: 'video'));

        $since = Carbon::now()->subMinute();

        expect($store->countVisitors([new Scope, new Scope('post'), new Scope('post', 7), new Scope('post', 7, 'amp'), new Scope('video', 8), new Scope('post', 9)], $since))
            ->toBe([3, 2, 2, 1, 1, 0])
            ->and($store->countVisitors([], $since))->toBeEmpty();
    })->with('presence clients');

    it('only counts the visitors seen since the moment asked for', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one'));

        Carbon::setTestNow('2026-10-06 12:02:30');

        $store->touch(redisSighting('two'));

        expect($store->countVisitors([new Scope('post', 7)], Carbon::now()->subMinute()))->toBe([1])
            ->and($store->countVisitors([new Scope('post', 7)], Carbon::now()->subMinutes(5)))->toBe([2]);
    })->with('presence clients');

    it('drops the visitors that fell out of the window on the next write', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one'));

        Carbon::setTestNow('2026-10-06 12:10:30');

        $store->touch(redisSighting('two'));

        expect($store->countVisitors([new Scope('post', 7)], Carbon::now()->subHour()))->toBe([1])
            ->and((int) presenceRedis()->command('ttl', ['{eloquent-viewable:live}:visitors:post|7|*']))->toBe(660);
    })->with('presence clients');

    it('stops counting a visitor who leaves the viewable', function (string $client): void {
        $store = useRedisPresence($client);
        $viewer = new Reference('user', 3);

        $store->touch(redisSighting('one', collection: 'amp', viewer: $viewer));
        $store->touch(redisSighting('two'));
        $store->leave(redisSighting('one', collection: 'amp', viewer: $viewer));

        $since = Carbon::now()->subMinute();

        expect($store->countVisitors([new Scope('post', 7), new Scope('post', 7, 'amp'), new Scope], $since))->toBe([1, 0, 2])
            ->and($store->viewers(new Scope('post', 7), $since, 10))->toBeEmpty()
            ->and(redisReferences($store->viewers(new Scope, $since, 10)))->toBe(['user|3']);
    })->with('presence clients');

    it('lets a guest leave', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one'));
        $store->leave(redisSighting('one'));

        expect($store->countVisitors([new Scope('post', 7)], Carbon::now()->subMinute()))->toBe([0]);
    })->with('presence clients');

    it('lists the viewables seen, the most recent first', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one', key: 1));
        Carbon::setTestNow('2026-10-06 12:01:30');
        $store->touch(redisSighting('one', key: 2, type: 'App\Models\Video'));
        Carbon::setTestNow('2026-10-06 12:02:30');
        $store->touch(redisSighting('one', key: 3));

        $since = Carbon::now()->subMinutes(5);

        expect(redisReferences($store->active(null, $since, 10)))->toBe(['post|3', 'App%5CModels%5CVideo|2', 'post|1'])
            ->and($store->active(null, $since, 10)[1]->type)->toBe('App\Models\Video')
            ->and(redisReferences($store->active('post', $since, 10)))->toBe(['post|3', 'post|1'])
            ->and(redisReferences($store->active(null, $since, 1)))->toBe(['post|3'])
            ->and($store->active(null, Carbon::now(), 10))->toBeEmpty();
    })->with('presence clients');

    it('lists the viewers seen, the most recent first', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one', viewer: new Reference('user', 1)));
        Carbon::setTestNow('2026-10-06 12:01:30');
        $store->touch(redisSighting('two', viewer: new Reference('user', 2)));
        $store->touch(redisSighting('three'));

        expect(redisReferences($store->viewers(new Scope('post', 7), Carbon::now()->subMinutes(5), 10)))->toBe(['user|2', 'user|1']);
    })->with('presence clients');

    it('keeps every key in one hash tag under the connection prefix', function (string $client): void {
        $store = useRedisPresence($client);

        $store->touch(redisSighting('one'));

        $keys = presenceRedis()->command('keys', ['*']);

        expect($keys)->toBeArray()->not->toBeEmpty()->each->toContain('{eloquent-viewable:live}:');
    })->with('presence clients');
});

describe('approximate', function (): void {
    it('counts the visitors of every minute the window touches', function (string $client): void {
        $store = useRedisPresence($client, 'approximate');

        $store->touch(redisSighting('one', collection: 'amp'));
        $store->touch(redisSighting('one'));
        $store->touch(redisSighting('two'));

        Carbon::setTestNow('2026-10-06 12:03:30');

        $store->touch(redisSighting('three'));

        expect($store->countVisitors([new Scope('post', 7), new Scope('post', 7, 'amp'), new Scope], Carbon::now()->subMinutes(5)))->toBe([3, 1, 3])
            ->and($store->countVisitors([new Scope('post', 7)], Carbon::now()->subMinute()))->toBe([1]);
    })->with('presence clients');

    it('keeps a visitor who leaves until their minute passes', function (string $client): void {
        $store = useRedisPresence($client, 'approximate');

        $store->touch(redisSighting('one'));
        $store->leave(redisSighting('one'));

        expect($store->countVisitors([new Scope('post', 7)], Carbon::now()->subMinute()))->toBe([1]);
    })->with('presence clients');

    it('still ranks and lists viewers from sorted sets', function (string $client): void {
        $store = useRedisPresence($client, 'approximate');

        $store->touch(redisSighting('one', viewer: new Reference('user', 1)));

        $since = Carbon::now()->subMinute();

        expect(redisReferences($store->active(null, $since, 10)))->toBe(['post|7'])
            ->and(redisReferences($store->viewers(new Scope('post', 7), $since, 10)))->toBe(['user|1']);
    })->with('presence clients');
});

describe('failures', function (): void {
    it('reports a write Redis refuses rather than throwing it', function (string $client): void {
        Exceptions::fake();

        $store = useRedisPresence($client);

        presenceRedis()->command('set', ['{eloquent-viewable:live}:visitors:*|*|*', 'not a sorted set']);

        $store->touch(redisSighting('one'));
        $store->leave(redisSighting('one'));

        Exceptions::assertReportedCount(1);
    })->with('presence clients');

    it('throws on a read Redis refuses', function (string $client): void {
        $store = useRedisPresence($client);

        presenceRedis()->command('set', ['{eloquent-viewable:live}:visitors:*|*|*', 'not a sorted set']);

        expect(fn (): array => $store->countVisitors([new Scope], Carbon::now()->subMinute()))
            ->toThrow(Exception::class, 'WRONGTYPE');
    })->with('presence clients');

    it('names the error phpredis kept', function (): void {
        $store = useRedisPresence('phpredis');

        presenceRedis()->command('set', ['{eloquent-viewable:live}:visitors:*|*|*', 'not a sorted set']);

        expect(fn (): array => $store->countVisitors([new Scope], Carbon::now()->subMinute()))
            ->toThrow(PresenceFailed::class, 'Redis refused a presence script: WRONGTYPE');
    });

    it('says so when a client answers false without an error', function (): void {
        $connection = Mockery::mock(PredisConnection::class);
        $connection->allows('eval')->andReturn(false);
        $connection->allows('client')->andReturn(new stdClass);

        $store = new RedisPresenceStore($connection, 'live', 300, Precision::Exact, app(ExceptionHandler::class));

        expect(fn (): array => $store->active(null, Carbon::now(), 10))
            ->toThrow(PresenceFailed::class, 'Redis refused a presence script: no error was given');
    });
});
