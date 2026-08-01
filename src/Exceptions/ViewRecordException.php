<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Exception;

final class ViewRecordException extends Exception
{
    public static function cannotRecordViewForViewableType(): static
    {
        return new self('Cannot record a view for a viewable type.');
    }
}
