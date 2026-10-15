<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Exceptions\PresenceFailed;
use CyrildeWit\EloquentViewable\Presence\Stores\ArrayPresenceStore;
use CyrildeWit\EloquentViewable\Presence\Stores\NullPresenceStore;
use CyrildeWit\EloquentViewable\Presence\Stores\PresenceManager;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

it('hands out the null store while presence is off', function (): void {
    config()->set('eloquent-viewable.presence.driver', 'array');

    expect(app(PresenceStore::class))->toBeInstanceOf(NullPresenceStore::class);
});

it('hands out the configured store once presence is on', function (): void {
    config()->set('eloquent-viewable.presence.enabled', true);
    config()->set('eloquent-viewable.presence.driver', 'array');

    expect(app(PresenceStore::class))->toBeInstanceOf(ArrayPresenceStore::class)
        ->and(app(PresenceStore::class))->toBe(app(PresenceStore::class));
});

it('refuses a driver that is not registered', function (): void {
    config()->set('eloquent-viewable.presence.enabled', true);
    config()->set('eloquent-viewable.presence.driver', 'memcached');

    expect(fn (): PresenceStore => app(PresenceStore::class))
        ->toThrow(InvalidConfiguration::class, 'The `eloquent-viewable.presence.driver` config value names a driver that is not registered, `memcached` given.');
});

it('takes a driver of your own', function (): void {
    config()->set('eloquent-viewable.presence.enabled', true);
    config()->set('eloquent-viewable.presence.driver', 'custom');

    $store = new ArrayPresenceStore;

    app(PresenceManager::class)->extend('custom', fn (): PresenceStore => $store);

    expect(app(PresenceStore::class))->toBe($store);
});

it('refuses a Redis connection it cannot run scripts on', function (): void {
    config()->set('eloquent-viewable.presence.enabled', true);

    $connection = Mockery::mock(Connection::class);
    $redis = Mockery::mock(RedisFactory::class);
    $redis->allows('connection')->andReturn($connection);

    app()->instance(RedisFactory::class, $redis);

    expect(fn (): PresenceStore => app(PresenceStore::class))
        ->toThrow(PresenceFailed::class, 'Presence runs on phpredis and Predis, the Redis connection is a `'.$connection::class.'`.');
});
