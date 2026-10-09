<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidOperator extends InvalidArgumentException implements EloquentViewableException
{
    public static function notAComparison(string $operator, string $allowed): self
    {
        return new self("whereViewsCount() compares a count, so it takes one of {$allowed}, '{$operator}' given.");
    }
}
