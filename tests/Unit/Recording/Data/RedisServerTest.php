<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Data\RedisServer;

it('reads Redis from the info fields', function (): void {
    expect(RedisServer::fromInfo(['redis_version' => '7.4.11']))->toEqual(new RedisServer('Redis', '7.4.11', '7.4.11'));
});

it('reads Valkey and the Redis release it is compatible with', function (): void {
    $server = RedisServer::fromInfo([
        'redis_version' => '7.2.4',
        'server_name' => 'valkey',
        'valkey_version' => '9.1.3',
    ]);

    expect($server)->toEqual(new RedisServer('Valkey', '9.1.3', '7.2.4'))
        ->and($server?->label())->toBe('Valkey 9.1.3');
});

it('reads the fields under the section Predis nests them in', function (): void {
    expect(RedisServer::fromInfo(['Server' => ['redis_version' => '7.4.11']])?->label())->toBe('Redis 7.4.11');
});

it('reads a server that names itself Valkey without its own version as Redis', function (): void {
    expect(RedisServer::fromInfo(['redis_version' => '7.2.4', 'server_name' => 'valkey'])?->label())->toBe('Redis 7.2.4');
});

it('reads nothing without a Redis version', function (array $info): void {
    expect(RedisServer::fromInfo($info))->toBeNull();
})->with([
    'no fields' => [[]],
    'not a string' => [['redis_version' => 7]],
    'an empty section' => [['Server' => []]],
]);
