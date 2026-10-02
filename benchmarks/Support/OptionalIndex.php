<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Benchmarks\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

/**
 * The optional indexes the README suggests for apps that need them. The
 * migration stub does not create these, so `make bench-indexes` adds them to
 * a seeded dataset and the same benchmarks run with and without.
 */
enum OptionalIndex: string
{
    /**
     * `visitor` as a fourth column of the composite index, so a unique count
     * over a period is index-only. Postgres gets it as an INCLUDE column.
     */
    case Visitor = 'visitor';

    /**
     * `(viewable_type, viewed_at)`, which serves a count over a whole type
     * within a period. The composite index cannot narrow that by date.
     */
    case TypeViewedAt = 'type-viewed-at';

    /**
     * @return list<self>
     */
    public static function fromList(string $list): array
    {
        if (trim($list) === '' || $list === 'none') {
            return [];
        }

        return array_map(
            static fn (string $value): self => self::tryFrom(trim($value))
                ?? throw new InvalidArgumentException(
                    "Unknown index [{$value}]. Choose from: ".implode(', ', array_column(self::cases(), 'value')).', or none.'
                ),
            explode(',', $list),
        );
    }

    /**
     * @param  list<self>  $indexes
     */
    public static function toList(array $indexes): string
    {
        return implode(',', array_map(static fn (self $index): string => $index->value, $indexes));
    }

    public function name(): string
    {
        return match ($this) {
            self::Visitor => 'views_viewable_viewed_at_visitor_index',
            self::TypeViewedAt => 'views_viewable_type_viewed_at_index',
        };
    }

    public function create(ConnectionInterface $connection, string $table): void
    {
        if ($this === self::Visitor && $connection->getDriverName() === 'pgsql') {
            $connection->statement(
                "create index {$this->name()} on {$table} (viewable_type, viewable_id, viewed_at) include (visitor)"
            );

            return;
        }

        $connection->getSchemaBuilder()->table($table, function (Blueprint $blueprint): void {
            $blueprint->index($this->columns(), $this->name());
        });
    }

    public function drop(ConnectionInterface $connection, string $table): void
    {
        $connection->getSchemaBuilder()->table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex($this->name());
        });
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return match ($this) {
            self::Visitor => ['viewable_type', 'viewable_id', 'viewed_at', 'visitor'],
            self::TypeViewedAt => ['viewable_type', 'viewed_at'],
        };
    }
}
