<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Grammars\SQLiteGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new SQLiteGrammar)->truncate('"viewed_at"', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "strftime('%Y-%m-%d %H:00:00', \"viewed_at\")"],
    'day' => [Granularity::Day, "strftime('%Y-%m-%d 00:00:00', \"viewed_at\")"],
    'week' => [Granularity::Week, "strftime('%Y-%m-%d 00:00:00', \"viewed_at\", '-' || ((strftime('%w', \"viewed_at\") + 6) % 7) || ' days')"],
    'month' => [Granularity::Month, "strftime('%Y-%m-01 00:00:00', \"viewed_at\")"],
    'year' => [Granularity::Year, "strftime('%Y-01-01 00:00:00', \"viewed_at\")"],
]);
