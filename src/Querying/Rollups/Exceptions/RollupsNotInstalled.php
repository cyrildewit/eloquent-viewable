<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Support\OptionalTable;
use Exception;

final class RollupsNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self(OptionalTable::Rollups->notInstalled($table));
    }

    /**
     * The folds are marked in the retention state table, which is published
     * under the retention tag.
     */
    public static function missingStateTable(string $table): self
    {
        return new self(OptionalTable::Rollups->missing($table).' '.OptionalTable::Retention->hint());
    }
}
