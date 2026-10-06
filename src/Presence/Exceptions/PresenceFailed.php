<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class PresenceFailed extends Exception implements EloquentViewableException
{
    public static function unsupportedRedisClient(string $class): self
    {
        return new self("Presence runs on phpredis and Predis, the Redis connection is a `{$class}`.");
    }

    public static function redisScript(string $error): self
    {
        return new self("Redis refused a presence script: {$error}");
    }
}
