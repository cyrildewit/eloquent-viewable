<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use InvalidArgumentException;

final class InvalidViewer extends InvalidArgumentException implements EloquentViewableException
{
    public static function cannotNarrowAlsoViewed(): self
    {
        return new self('alsoViewed() ranks across every visitor, so it cannot be narrowed to one viewer. Drop viewedBy(), or use whereViewedBy() to list what one viewer has seen.');
    }

    public static function unsupportedKey(string $class, mixed $key): self
    {
        $type = get_debug_type($key);

        return new self("The key of the viewer [{$class}] must be an integer or a string, {$type} given.");
    }
}
