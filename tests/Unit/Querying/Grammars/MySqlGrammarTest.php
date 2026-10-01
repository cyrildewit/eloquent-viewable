<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Grammars\MySqlGrammar;
use CyrildeWit\EloquentViewable\Support\Granularity;

it('truncates to the {granularity}', function (Granularity $granularity, string $expected): void {
    expect((new MySqlGrammar)->truncate('`viewed_at`', $granularity))->toBe($expected);
})->with([
    'hour' => [Granularity::Hour, "date_format(`viewed_at`, '%Y-%m-%d %H:00:00')"],
    'day' => [Granularity::Day, "date_format(`viewed_at`, '%Y-%m-%d 00:00:00')"],
    'week' => [Granularity::Week, "date_format(date_sub(`viewed_at`, interval weekday(`viewed_at`) day), '%Y-%m-%d 00:00:00')"],
    'month' => [Granularity::Month, "date_format(`viewed_at`, '%Y-%m-01 00:00:00')"],
    'year' => [Granularity::Year, "date_format(`viewed_at`, '%Y-01-01 00:00:00')"],
]);
