<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use InvalidArgumentException;

final class InvalidViewer extends InvalidArgumentException implements EloquentViewableException
{
    public static function unsupportedKey(string $class, mixed $key): self
    {
        return new self(sprintf('The key of the viewer [%s] must be an integer or a string, %s given.', $class, get_debug_type($key)));
    }
}
