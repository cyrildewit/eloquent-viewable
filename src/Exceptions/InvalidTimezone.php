<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Exception;

final class InvalidTimezone extends Exception implements EloquentViewableException
{
    public static function notAnIdentifier(string $timezone): self
    {
        return new self("`{$timezone}` is not a timezone identifier. Use a name such as `Europe/Amsterdam` or `UTC`, not an offset or an abbreviation.");
    }
}
