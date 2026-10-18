<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Illuminate\Redis\Connections\Connection;
use Redis;

/**
 * phpredis answers an error reply with `false` and keeps the error until it
 * is read, so a command that failed has to read it off the client, which
 * clears it, or the next command fails too. Predis throws instead, so a
 * connection of another client has no error to give.
 *
 * @internal
 */
final class RedisError
{
    public static function last(Connection $connection, string $default = ''): string
    {
        $client = $connection->client();

        if (! $client instanceof Redis) {
            return $default;
        }

        $error = (string) $client->getLastError();

        $client->clearLastError();

        return $error;
    }
}
