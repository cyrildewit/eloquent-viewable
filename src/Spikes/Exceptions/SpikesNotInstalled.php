<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class SpikesNotInstalled extends Exception implements EloquentViewableException
{
    public static function missingTable(string $table): self
    {
        return new self("Spikes are configured, but the `{$table}` table does not exist. Publish its migration with `php artisan vendor:publish --tag=eloquent-viewable-spikes` and run `php artisan migrate`.");
    }
}
