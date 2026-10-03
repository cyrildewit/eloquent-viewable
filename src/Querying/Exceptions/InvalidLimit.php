<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidLimit extends InvalidArgumentException implements EloquentViewableException
{
    public static function belowOne(int $limit): self
    {
        return new self("top() needs a limit of at least one, {$limit} given.");
    }
}
