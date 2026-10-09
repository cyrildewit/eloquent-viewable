<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Grammars;

use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\Concerns\ConvertsByOffset;
use CyrildeWit\EloquentViewable\Support\Granularity;

/**
 * Serves both the `mysql` and `mariadb` drivers.
 */
final readonly class MySqlGrammar implements BucketGrammar
{
    use ConvertsByOffset;

    public function truncate(string $column, Granularity $granularity): string
    {
        return match ($granularity) {
            Granularity::Hour => "date_format({$column}, '%Y-%m-%d %H:00:00')",
            Granularity::Day => "date_format({$column}, '%Y-%m-%d 00:00:00')",
            // weekday() is Monday-based and unaffected by default_week_format.
            Granularity::Week => "date_format(date_sub({$column}, interval weekday({$column}) day), '%Y-%m-%d 00:00:00')",
            Granularity::Month => "date_format({$column}, '%Y-%m-01 00:00:00')",
            Granularity::Year => "date_format({$column}, '%Y-01-01 00:00:00')",
        };
    }

    protected function shift(string $column, int $seconds): string
    {
        return "date_add({$column}, interval {$seconds} second)";
    }
}
