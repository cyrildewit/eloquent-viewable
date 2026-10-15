<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class MilestonesNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self("Milestones are configured, but the `{$table}` table does not exist. Publish its migration with `php artisan vendor:publish --tag=eloquent-viewable-milestones` and run `php artisan migrate`.");
    }
}
