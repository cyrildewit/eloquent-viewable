<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Support\Indexes;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Generator;
use Illuminate\Database\Schema\Builder;

class SchemaCheck implements Check
{
    /** @var list<string> */
    public const array Columns = [
        'viewable_type',
        'viewable_id',
        'viewer_type',
        'viewer_id',
        'visitor',
        'collection',
        'context',
        'viewed_at',
    ];

    /** @var array<string, list<string>> */
    public const array Indexes = [
        'viewable_viewed_at' => ['viewable_type', 'viewable_id', 'viewed_at'],
        'viewed_at' => ['viewed_at'],
    ];

    protected const string UpgradeGuide = 'The upgrade guide has a migration that adds it, under "Add the new columns and indexes" in UPGRADING.md.';

    public function __construct(
        protected View $view,
        protected Config $config,
        protected RetentionPolicy $retention,
        protected RollupPolicy $rollups,
        protected RetentionState $state,
    ) {}

    public function name(): string
    {
        return 'Database schema';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function run(): Generator
    {
        yield from $this->viewsTable();
        yield from $this->retentionTable();
        yield from $this->rollupTable();
        yield from $this->counterColumns();
        yield from $this->milestonesTable();
    }

    /** @return Generator<int, Finding> */
    protected function viewsTable(): Generator
    {
        $schema = $this->view->getConnection()->getSchemaBuilder();
        $table = $this->view->getTable();

        if (! $schema->hasTable($table)) {
            yield Finding::failure(
                "The `{$table}` table does not exist.",
                'Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"` and run `php artisan migrate`.',
            );

            return;
        }

        $complete = true;

        $missing = $this->missingColumns($schema, $table, self::Columns);

        if ($missing !== []) {
            $complete = false;

            $list = $this->list($missing);

            yield Finding::failure("The `{$table}` table has no {$list} column.", self::UpgradeGuide);
        }

        $indexes = Indexes::of($schema, $table);

        foreach (self::Indexes as $columns) {
            if ($indexes->cover($columns)) {
                continue;
            }

            $complete = false;

            $list = implode(', ', $columns);

            yield Finding::failure("The `{$table}` table has no index on `({$list})`.", self::UpgradeGuide);
        }

        if ($complete) {
            yield Finding::pass("The `{$table}` table has every column and index the package needs.");
        }
    }

    /** @return Generator<int, Finding> */
    protected function retentionTable(): Generator
    {
        if (! $this->retention->anonymiseAfter instanceof Duration && ! $this->retention->pruneAfter instanceof Duration) {
            return;
        }

        if ($this->state->installed()) {
            yield Finding::pass('The retention state table exists.');

            return;
        }

        $table = RetentionState::Table;

        yield Finding::failure(
            "Retention is configured, but the `{$table}` table does not exist.",
            'Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="eloquent-viewable-retention"` and run `php artisan migrate`.',
        );
    }

    /** @return Generator<int, Finding> */
    protected function rollupTable(): Generator
    {
        if (! $this->rollups->isEnabled()) {
            return;
        }

        $rollup = new ViewRollup;
        $table = $rollup->getTable();

        if ($rollup->getConnection()->getSchemaBuilder()->hasTable($table)) {
            yield Finding::pass("The `{$table}` table exists.");

            return;
        }

        yield Finding::failure(
            "Rollups are configured, but the `{$table}` table does not exist.",
            'Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="eloquent-viewable-rollups"` and run `php artisan migrate`.',
        );
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function counterColumns(): Generator
    {
        foreach ($this->config->counters() as $class => $columns) {
            $model = new $class;
            $table = $model->getTable();
            $schema = $model->getConnection()->getSchemaBuilder();

            $missing = $this->missingColumns($schema, $table, array_keys($columns));

            if ($missing === []) {
                yield Finding::pass("The `{$table}` table has every counter column in `querying.counters`.");

                continue;
            }

            $list = $this->list($missing);

            yield Finding::failure(
                "The `{$table}` table has no {$list} column, which `querying.counters` writes to.",
                'Add the column in a migration of your own, as an unsigned integer that defaults to 0.',
            );
        }
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function milestonesTable(): Generator
    {
        if ($this->config->milestones() === []) {
            return;
        }

        $table = $this->config->milestonesTable();

        if ($this->view->getConnection()->getSchemaBuilder()->hasTable($table)) {
            yield Finding::pass("The `{$table}` table exists.");

            return;
        }

        yield Finding::failure(
            "Milestones are configured, but the `{$table}` table does not exist.",
            'Publish the migration with `php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="eloquent-viewable-milestones"` and run `php artisan migrate`.',
        );
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    protected function missingColumns(Builder $schema, string $table, array $columns): array
    {
        return array_values(array_diff($columns, $schema->getColumnListing($table)));
    }

    /** @param  list<string>  $columns */
    protected function list(array $columns): string
    {
        return implode(', ', array_map(fn (string $column): string => "`{$column}`", $columns));
    }
}
