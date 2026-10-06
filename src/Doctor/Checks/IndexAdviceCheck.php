<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Support\Indexes;
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
    public const int LargeTable = 1_000_000;

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
        $rows = $this->estimateRows($table);
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

        if (! $reported) {
            yield Finding::pass('No other index is needed yet: nothing in the config relies on one, and the views table is small.');
        }
    }

    /**
     * The largest key stands in for the number of rows: it is one index
     * lookup on every driver, where a count reads the whole table.
     */
    protected function estimateRows(string $table): int
    {
        $max = $this->view->getConnection()->table($table)->max($this->view->getKeyName());

        return is_numeric($max) ? (int) $max : 0;
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function reasonFor(OptionalIndex $index): ?string
    {
        if ($index !== OptionalIndex::Visitor) {
            return null;
        }

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
            OptionalIndex::TypeViewedAt => 'counts over a whole model type, such as `views(Post::class)->count()` and `orderByTrending()`',
            OptionalIndex::VisitorHistory => '`alsoViewed()`',
            OptionalIndex::ViewedAt => 'retention and rollups',
        };
    }
}
