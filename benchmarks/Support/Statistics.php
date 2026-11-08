<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Illuminate\Database\ConnectionInterface;

/**
 * Refreshes the planner statistics of a table that was just filled, so the
 * query plans the benchmarks and `make bench-explain` get are the ones a
 * production table would get. Postgres also needs a vacuum to set the
 * visibility map, without which it never chooses an index-only scan over a
 * freshly loaded table.
 */
final class Statistics
{
    public static function refresh(ConnectionInterface $connection, string $table): void
    {
        match ($connection->getDriverName()) {
            'sqlite' => $connection->statement("analyze {$table}"),
            'pgsql' => $connection->statement("vacuum analyze {$table}"),
            'mysql', 'mariadb' => $connection->statement("analyze table {$table}"),
            default => null,
        };
    }
}
