<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Grammars;

use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;

final readonly class PostgresGrammar implements BucketGrammar
{
    public function truncate(string $column, Granularity $granularity): string
    {
        // date_trunc('week') follows ISO 8601, so weeks always start on Monday.
        return "to_char(date_trunc('{$granularity->value}', {$column}), 'YYYY-MM-DD HH24:MI:SS')";
    }
}
