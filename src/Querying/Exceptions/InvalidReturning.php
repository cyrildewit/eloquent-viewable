<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use LogicException;

final class InvalidReturning extends LogicException implements EloquentViewableException
{
    public static function onlyCounted(string $method): self
    {
        return new self("returning() narrows count() and compare() to visitors who came back on another day, so {$method} cannot read it. Call {$method} without returning(), or use countByFrequency().");
    }
}
