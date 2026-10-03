<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;
use Illuminate\Redis\Connections\Connection;

final class UnsupportedRedisClient extends Exception implements EloquentViewableException
{
    public static function forConnection(Connection $connection): self
    {
        $class = $connection::class;

        return new self("The `redis` view store needs a phpredis or Predis connection, `{$class}` given.");
    }
}
