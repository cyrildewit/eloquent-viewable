<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Support\OptionalTable;
use Exception;

final class MilestonesNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self(OptionalTable::Milestones->notInstalled($table));
    }
}
