<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class RedisStreamFailed extends Exception implements EloquentViewableException
{
    public static function creatingGroup(string $stream, string $group, string $error): self
    {
        return new self("Could not create the consumer group `{$group}` on the Redis stream `{$stream}`: {$error}");
    }

    public static function malformedEntry(string $id): self
    {
        return new self("The entry `{$id}` of the Redis view stream is missing the fields of a view record.");
    }
}
