<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Support\Indexes;
use CyrildeWit\EloquentViewable\Doctor\Support\TableSize;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\OptionalIndex;
use Generator;

/**
 * Which queries an app runs cannot be seen from the console, so an index is
 * recommended for what the config relies on, and for the calls it would
 * speed up once the views table is large enough for them to hurt.
 */
class IndexAdviceCheck implements Check
{
    public const int LargeTable = TableSize::Large;

    /**
     * The indexes the migration leaves out. `viewed_at` on its own is
     * created by the migration, so the schema check covers it.
     *
     * @var list<OptionalIndex>
     */
    public const array Recommended = [
        OptionalIndex::Visitor,
        OptionalIndex::TypeViewedAt,
        OptionalIndex::VisitorHistory,
    ];

    public function __construct(
        protected View $view,
        protected Config $config,
        protected DimensionRegistry $dimensions,
    ) {}

    public function name(): string
    {
        return 'Optional indexes';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function run(): Generator
    {
        $connection = $this->view->getConnection();
        $schema = $connection->getSchemaBuilder();
        $table = $this->view->getTable();

        if (! $schema->hasTable($table)) {
            yield Finding::skipped("The `{$table}` table does not exist yet.");

            return;
        }

        $indexes = Indexes::of($schema, $table);
        $rows = TableSize::estimate($this->view);
        $large = $rows >= self::LargeTable;
        $reported = false;

        foreach (self::Recommended as $index) {
            $columns = implode(', ', $index->columns());

            if ($indexes->cover($index->columns())) {
                $reported = true;

                yield Finding::pass("The `({$columns})` index is in place.");

                continue;
            }

            $reason = $this->reasonFor($index);

            if ($reason === null && ! $large) {
                continue;
            }

            $reported = true;
            $speedsUp = $this->speedsUp($index);
            $fix = "Add it in a migration of your own: `{$index->migration($table, $connection->getDriverName())}`.";

            if ($reason === null) {
                $views = number_format($rows);

                yield Finding::advice("With about {$views} views, an index on `({$columns})` speeds up {$speedsUp}.", $fix);

                continue;
            }

            $summary = "Add an index on `({$columns})`: {$reason}, and it speeds up {$speedsUp}.";

            yield $large
                ? Finding::warning($summary, $fix)
                : Finding::advice($summary, $fix);
        }

        foreach ($this->unfoldedDimensions() as $name) {
            $columns = ['viewable_type', $name, 'viewed_at'];
            $list = implode(', ', $columns);

            if ($indexes->cover($columns)) {
                $reported = true;

                yield Finding::pass("The `({$list})` index is in place.");

                continue;
            }

            if (! $large) {
                continue;
            }

            $reported = true;
            $views = number_format($rows);

            yield Finding::advice(
                "With about {$views} views, an index on `({$list})` speeds up `whereDimension('{$name}', ...)` and `countBy('{$name}')` over a whole model type.",
                "Add it in a migration of your own: `\$table->index(['viewable_type', '{$name}', 'viewed_at']);`.",
            );
        }

        if (! $reported) {
            yield Finding::pass('No other index is needed yet: nothing in the config relies on one, and the views table is small.');
        }
    }

    /**
     * The dimensions kept in a column that rollups do not fold, whose history
     * across a type is only ever read from the views table.
     *
     * @return list<string>
     *
     * @throws InvalidConfiguration
     */
    protected function unfoldedDimensions(): array
    {
        return array_values(array_diff($this->dimensions->columns(), $this->config->rollupDimensions()));
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function reasonFor(OptionalIndex $index): ?string
    {
        return match ($index) {
            OptionalIndex::Visitor => $this->uniqueCounterReason(),
            OptionalIndex::TypeViewedAt => $this->spikesReason(),
            default => null,
        };
    }

    /**
     * Without the index, every window the detector compares walks the
     * composite index once per model of the type.
     *
     * @throws InvalidConfiguration
     */
    protected function spikesReason(): ?string
    {
        if ($this->config->spikes() === []) {
            return null;
        }

        return '`spikes.types` watches whole model types';
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function uniqueCounterReason(): ?string
    {
        foreach ($this->config->counters() as $columns) {
            foreach ($columns as $query) {
                if ($query->unique) {
                    return '`querying.counters` keeps a unique count';
                }
            }
        }

        return null;
    }

    protected function speedsUp(OptionalIndex $index): string
    {
        return match ($index) {
            OptionalIndex::Visitor => '`unique()` counts',
            OptionalIndex::TypeViewedAt => 'counts over a whole model type, such as `views(Post::class)->count()`, `orderByTrending()`, `rising()` and `anomalies()`',
            OptionalIndex::VisitorHistory => '`alsoViewed()`',
            OptionalIndex::ViewedAt => 'retention and rollups',
        };
    }
}
