<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Support\Granularity;

interface BucketGrammar
{
    /**
     * SQL that truncates an already-wrapped column to the start of its bucket,
     * formatted as `YYYY-MM-DD HH:MM:SS`. Weeks start on Monday.
     */
    public function truncate(string $column, Granularity $granularity): string;
}
