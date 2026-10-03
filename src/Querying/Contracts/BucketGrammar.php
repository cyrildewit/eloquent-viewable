<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Contracts;

use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Support\Granularity;

interface BucketGrammar
{
    /**
     * SQL that truncates an already-wrapped column to the start of its bucket,
     * formatted as `YYYY-MM-DD HH:MM:SS`. Weeks start on Monday.
     */
    public function truncate(string $column, Granularity $granularity): string;

    /**
     * SQL that reads an already-wrapped column as the wall clock of the
     * conversion's `from` zone and yields the wall clock of its `to` zone.
     * The result is handed to `truncate()` in place of the column.
     */
    public function convertTimezone(string $column, TimezoneConversion $conversion): string;
}
