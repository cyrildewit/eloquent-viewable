<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class RollupsNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self("The `{$table}` table does not exist. Publish its migration with `php artisan vendor:publish --tag=eloquent-viewable-rollups` and run `php artisan migrate`.");
    }
}
