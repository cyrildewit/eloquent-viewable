<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class InvalidInterval extends Exception implements EloquentViewableException
{
    public static function periodWithoutStartDateTime(): static
    {
        return new self('Counting views by interval requires a period with a start date time.');
    }

    public static function producesTooManyIntervals(int $intervals, int $maximum): static
    {
        return new self("The period and granularity produce {$intervals} intervals, which exceeds the configured maximum of {$maximum}.");
    }
}
