<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidLifetime extends InvalidArgumentException implements EloquentViewableException
{
    public static function staleWithoutLifetime(): self
    {
        return new self('remember() needs a lifetime to serve an entry stale after it.');
    }

    public static function staleNotAfterLifetime(): self
    {
        return new self('remember() keeps an entry until $stale, measured from now like the lifetime, so it must end after the lifetime.');
    }
}
