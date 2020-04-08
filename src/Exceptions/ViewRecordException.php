<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Exceptions;

use Exception;

class ViewRecordException extends Exception
{
    /**
     * Cannot record a view for a viewable.
     *
     * @return static
     */
    public static function cannotRecordViewForViewableType()
    {
        return new static('Cannot record a view for a viewable type.');
    }
}
