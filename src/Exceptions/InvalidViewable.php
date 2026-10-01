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
}
