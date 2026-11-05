<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Recording\Exceptions\UnsupportedRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\ClientFactory;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\PhpRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Clients\PredisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

it('talks phpredis to a phpredis connection', function (): void {
    expect(ClientFactory::make(Mockery::mock(PhpRedisConnection::class)))->toBeInstanceOf(PhpRedisClient::class);
});

it('talks Predis to a Predis connection', function (): void {
    expect(ClientFactory::make(Mockery::mock(PredisConnection::class)))->toBeInstanceOf(PredisClient::class);
});

it('refuses a connection it cannot talk to', function (): void {
    expect(fn (): StreamClient => ClientFactory::make(Mockery::mock(Connection::class)))
        ->toThrow(UnsupportedRedisClient::class, 'The `redis` view store needs a phpredis or Predis connection, `');
});
