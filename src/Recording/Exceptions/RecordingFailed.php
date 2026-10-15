<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class RecordingFailed extends Exception implements EloquentViewableException
{
    public static function cannotRecordViewForViewableType(): self
    {
        return new self('Cannot record a view for a viewable type.');
    }
}
