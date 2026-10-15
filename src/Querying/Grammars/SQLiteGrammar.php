<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Grammars;

use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Grammars\Concerns\ConvertsByOffset;
use CyrildeWit\EloquentViewable\Support\Granularity;

final readonly class SQLiteGrammar implements BucketGrammar
{
    use ConvertsByOffset;

    public function truncate(string $column, Granularity $granularity): string
    {
        return match ($granularity) {
            Granularity::Hour => "strftime('%Y-%m-%d %H:00:00', {$column})",
            Granularity::Day => "strftime('%Y-%m-%d 00:00:00', {$column})",
            // %w is 0 for Sunday, so (w + 6) % 7 is the number of days since Monday.
            Granularity::Week => "strftime('%Y-%m-%d 00:00:00', {$column}, '-' || ((strftime('%w', {$column}) + 6) % 7) || ' days')",
            Granularity::Month => "strftime('%Y-%m-01 00:00:00', {$column})",
            Granularity::Year => "strftime('%Y-01-01 00:00:00', {$column})",
        };
    }

    protected function shift(string $column, int $seconds): string
    {
        return sprintf("datetime(%s, '%+d seconds')", $column, $seconds);
    }
}
