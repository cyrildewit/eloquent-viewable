<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidFrequency extends InvalidArgumentException implements EloquentViewableException
{
    public static function capBelowTwo(int $upTo): self
    {
        return new self("countByFrequency() needs a cap of at least two, so new and returning visitors stay apart. {$upTo} given.");
    }
}
