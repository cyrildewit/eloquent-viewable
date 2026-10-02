<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Streams\Clients;

use CyrildeWit\EloquentViewable\Recording\Exceptions\UnsupportedRedisClient;
use CyrildeWit\EloquentViewable\Recording\Streams\Contracts\StreamClient;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;

/** @internal */
final class ClientFactory
{
    /** @throws UnsupportedRedisClient */
    public static function make(Connection $connection): StreamClient
    {
        return match (true) {
            $connection instanceof PhpRedisConnection => new PhpRedisClient($connection),
            $connection instanceof PredisConnection => new PredisClient($connection),
            default => throw UnsupportedRedisClient::forConnection($connection),
        };
    }
}
