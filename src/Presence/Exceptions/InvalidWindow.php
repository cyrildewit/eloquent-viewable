<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidWindow extends InvalidArgumentException implements EloquentViewableException
{
    public static function outOfRange(int $seconds, int $window): self
    {
        return new self("within() needs between 1 and {$window} seconds, the `presence.window` presence is kept for, {$seconds} given.");
    }
}
