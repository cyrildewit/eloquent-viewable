<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Support;

use CyrildeWit\EloquentViewable\Models\View;

/**
 * How many rows the views table holds, roughly. The largest key stands in for
 * the number of rows: it is one index lookup on every driver, where a count
 * reads the whole table.
 */
class TableSize
{
    public const int Large = 1_000_000;

    public static function estimate(View $view): int
    {
        $max = $view->getConnection()->table($view->getTable())->max($view->getKeyName());

        return is_numeric($max) ? (int) $max : 0;
    }
}
