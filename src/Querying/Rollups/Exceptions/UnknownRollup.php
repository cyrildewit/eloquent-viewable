<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class UnknownRollup extends Exception implements EloquentViewableException
{
    public static function named(string $name): self
    {
        return new self("No custom rollup is named `{$name}`. List its class under `eloquent-viewable.retention.rollups.custom`.");
    }

    public static function withoutDimension(?string $name): self
    {
        if ($name === null) {
            return new self('Counting by dimension needs a custom rollup with a dimension. Call `rollup()` with its name first.');
        }

        return new self("The `{$name}` rollup has no dimension to count by. Return a column or JSON path from its `dimension()` method.");
    }
}
