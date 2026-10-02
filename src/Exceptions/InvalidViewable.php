<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use InvalidArgumentException;

final class InvalidViewable extends InvalidArgumentException implements EloquentViewableException
{
    public static function classDoesNotImplementViewable(string $class): self
    {
        return new self(sprintf('Class [%s] must implement %s.', $class, Viewable::class));
    }

    public static function missing(): self
    {
        return new self('No viewable was given. Call forViewable() before counting, recording or destroying views.');
    }

    public static function unsupportedKey(string $class, mixed $key): self
    {
        return new self(sprintf('The key of [%s] must be an integer, a string or null, %s given.', $class, get_debug_type($key)));
    }
}
