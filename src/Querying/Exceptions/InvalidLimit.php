<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidLimit extends InvalidArgumentException implements EloquentViewableException
{
    public static function belowOne(int $limit, string $method = 'top()'): self
    {
        return new self("{$method} needs a limit of at least one, {$limit} given.");
    }
}
