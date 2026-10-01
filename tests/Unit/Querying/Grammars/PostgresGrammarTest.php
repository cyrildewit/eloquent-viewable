<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Grammars\PostgresGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new PostgresGrammar)->truncate('"viewed_at"', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "to_char(date_trunc('hour', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'day' => [Granularity::Day, "to_char(date_trunc('day', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'week' => [Granularity::Week, "to_char(date_trunc('week', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'month' => [Granularity::Month, "to_char(date_trunc('month', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
    'year' => [Granularity::Year, "to_char(date_trunc('year', \"viewed_at\"), 'YYYY-MM-DD HH24:MI:SS')"],
]);
