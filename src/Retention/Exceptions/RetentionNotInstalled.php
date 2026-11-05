<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Support\OptionalTable;
use Exception;

final class RetentionNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self(OptionalTable::Retention->notInstalled($table));
    }
}
