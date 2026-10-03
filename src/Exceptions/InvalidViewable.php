<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
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

    public static function cannotRankOne(Viewable $viewable): self
    {
        return new self(sprintf(
            'top() ranks every viewable of a type or every type. [%s] with key %s was given; pass a model without a key, or none at all.',
            $viewable::class,
            ViewableKey::of($viewable),
        ));
    }

    public static function missingSet(): self
    {
        return new self('No viewables were given. Call forViewables() before counting them.');
    }

    public static function missingKey(string $class): self
    {
        return new self(sprintf('Every viewable in a set needs a key, an unsaved [%s] was given.', $class));
    }

    public static function mixedTypes(string $expected, string $given): self
    {
        return new self(sprintf('Every viewable in a set must be of one type, [%s] and [%s] given.', $expected, $given));
    }

    public static function unsupportedKey(string $class, mixed $key): self
    {
        return new self(sprintf('The key of [%s] must be an integer, a string or null, %s given.', $class, get_debug_type($key)));
    }
}
