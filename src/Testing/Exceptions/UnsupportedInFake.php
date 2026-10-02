<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Testing\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use LogicException;

final class UnsupportedInFake extends LogicException implements EloquentViewableException
{
    public static function scopes(): self
    {
        return new self('The views fake keeps records in memory, so withViewsCount() and orderByViews() cannot read from it. Use the database for those queries.');
    }
}
