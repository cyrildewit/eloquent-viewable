<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Support;

use Illuminate\Database\Schema\Builder;

/**
 * The indexes of one table, matched by their columns rather than their names,
 * so an index added under a name of its own still counts.
 */
class Indexes
{
    /** @param  list<list<string>>  $indexes */
    public function __construct(
        protected array $indexes,
    ) {}

    public static function of(Builder $schema, string $table): self
    {
        $indexes = [];

        foreach ($schema->getIndexes($table) as $index) {
            $indexes[] = array_map(strtolower(...), $index['columns']);
        }

        return new self($indexes);
    }

    /**
     * An index serves the columns when it starts with them, in order, so
     * `(a, b, c)` serves `(a, b)` but not `(b, c)`.
     *
     * @param  list<string>  $columns
     */
    public function cover(array $columns): bool
    {
        return array_any($this->indexes, fn (array $index): bool => array_slice($index, 0, count($columns)) === $columns);
    }
}
