<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class UnknownDimension extends InvalidArgumentException implements EloquentViewableException
{
    public static function named(string $name): self
    {
        return new self("No dimension is named `{$name}`. List its class under `eloquent-viewable.dimensions.definitions`.");
    }
}
