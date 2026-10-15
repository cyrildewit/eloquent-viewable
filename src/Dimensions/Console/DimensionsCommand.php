<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Dimensions\Console;

use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Dimensions\Normaliser;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\LimitsRunTime;
use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

/**
 * Compares the dimensions in config with the views table and writes a
 * migration that adds the columns it lacks. With `--backfill`, it copies the
 * values a dimension kept in `context` into its new column instead.
 */
final class DimensionsCommand extends Command
{
    use LimitsRunTime;

    #[\Override]
    protected $signature = 'views:dimensions
        {--backfill= : Copy the values of this dimension from `context` into its column}
        {--from= : The JSON path to copy from, `context->{name}` by default}
        {--max-seconds= : Stop starting new chunks of the backfill after this many seconds}';

    #[\Override]
    protected $description = 'Write a migration that adds a column for every dimension the views table lacks';

    /** @throws InvalidConfiguration */
    public function handle(DimensionRegistry $registry, View $view, Config $config, Filesystem $files): int
    {
        $schema = $view->getConnection()->getSchemaBuilder();
        $table = $view->getTable();

        if (! $schema->hasTable($table)) {
            $this->components->error("The `{$table}` table does not exist. Run the migration that creates it first.");

            return self::FAILURE;
        }

        $backfill = $this->option('backfill');

        if (is_string($backfill)) {
            return $this->backfill($registry, $view, $config, $backfill);
        }

        $missing = array_values(array_diff($registry->columns(), $schema->getColumnListing($table)));

        if ($missing === []) {
            $this->components->info('Every dimension has its column.');

            return self::SUCCESS;
        }

        $path = $this->writeMigration($files, $missing);
        $list = implode('`, `', $missing);

        $this->components->info("Wrote a migration that adds `{$list}`: {$path}");
        $this->components->info('Run `php artisan migrate` to add the columns.');

        return self::SUCCESS;
    }

    /** @param  list<string>  $columns */
    private function writeMigration(Filesystem $files, array $columns): string
    {
        $name = Carbon::now()->format('Y_m_d_His').'_add_'.implode('_', $columns).'_dimensions_to_views_table.php';
        $path = $this->laravel->databasePath("migrations/{$name}");

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->migration($columns));

        return $path;
    }

    /** @param  list<string>  $columns */
    private function migration(array $columns): string
    {
        $list = "'".implode("', '", $columns)."'";
        $length = Normaliser::MaxLength;

        return <<<PHP
            <?php

            use Illuminate\\Database\\Migrations\\Migration;
            use Illuminate\\Database\\Schema\\Blueprint;
            use Illuminate\\Support\\Facades\\Schema;

            /*
             * Adds a column for each dimension in `eloquent-viewable.dimensions.definitions`
             * that the views table lacked when `php artisan views:dimensions` wrote this.
             */
            return new class extends Migration
            {
                protected array \$columns = [{$list}];

                public function up(): void
                {
                    \$schema = Schema::connection(config('eloquent-viewable.models.view.connection'));
                    \$table = config('eloquent-viewable.models.view.table_name');

                    foreach (\$this->columns as \$column) {
                        if (\$schema->hasColumn(\$table, \$column)) {
                            continue;
                        }

                        \$schema->table(\$table, function (Blueprint \$table) use (\$column) {
                            \$table->string(\$column, {$length})->nullable();
                        });
                    }
                }

                public function down(): void
                {
                    \$schema = Schema::connection(config('eloquent-viewable.models.view.connection'));
                    \$table = config('eloquent-viewable.models.view.table_name');

                    foreach (\$this->columns as \$column) {
                        if (! \$schema->hasColumn(\$table, \$column)) {
                            continue;
                        }

                        \$schema->table(\$table, function (Blueprint \$table) use (\$column) {
                            \$table->dropColumn(\$column);
                        });
                    }
                }
            };

            PHP;
    }

    /**
     * Copies the values in chunks of ids, each one statement, so a large
     * table is copied over several runs when `--max-seconds` stops one. A
     * view whose column already holds a value is left alone, so a second run
     * carries on where the first stopped.
     *
     * @throws InvalidConfiguration
     */
    private function backfill(DimensionRegistry $registry, View $view, Config $config, string $name): int
    {
        $definition = $registry->find($name);

        if (! $definition instanceof DimensionDefinition) {
            $this->components->error("No dimension is named `{$name}` in `eloquent-viewable.dimensions.definitions`.");

            return self::FAILURE;
        }

        if (! $definition->isColumn()) {
            $this->components->error("The `{$name}` dimension is kept in `context`. Keep it in a column before backfilling it.");

            return self::FAILURE;
        }

        $from = $this->from($name);

        if ($from === null) {
            $this->components->error('The --from option must be a path into context, such as `context->plan`.');

            return self::FAILURE;
        }

        $deadline = $this->deadline();

        if ($deadline === false) {
            return self::FAILURE;
        }

        $this->components->info("Copying `{$from}` into the `{$name}` column...");

        [$copied, $stopped] = $this->copy($view, $name, $from, $config->retentionChunk(), $deadline);

        $this->components->info("Copied {$copied} values.");

        if ($stopped) {
            $this->reportStopped();
        }

        return self::SUCCESS;
    }

    private function from(string $name): ?string
    {
        $from = $this->option('from');

        if (! is_string($from)) {
            return "context->{$name}";
        }

        if (preg_match('/^context(->[A-Za-z0-9_]+)+$/', $from) !== 1) {
            return null;
        }

        return $from;
    }

    /** @return array{int, bool} */
    private function copy(View $view, string $column, string $from, int $chunk, Deadline $deadline): array
    {
        $grammar = $view->getConnection()->getQueryGrammar();
        $key = $view->getKeyName();
        $value = "substr({$grammar->wrap($from)}, 1, ".Normaliser::MaxLength.')';
        $copied = 0;

        $pending = static fn (): Builder => $view->newQuery()->toBase()->whereNull($column)->whereNotNull($from);
        $first = $pending()->min($key);
        $last = $pending()->max($key);

        if (! is_numeric($first)) {
            return [0, false];
        }

        // Both bounds come from the same rows, so the last is a number
        // whenever the first is.
        $end = is_numeric($last) ? (int) $last : (int) $first;

        for ($cursor = (int) $first; $cursor <= $end; $cursor += $chunk) {
            if ($deadline->passed()) {
                return [$copied, true];
            }

            $copied += $pending()
                ->where($key, '>=', $cursor)
                ->where($key, '<', $cursor + $chunk)
                ->update([$column => $view->getConnection()->raw($value)]); // @phpstan-ignore argument.type (a wrapped path checked against a pattern and an integer literal, not user input)
        }

        return [$copied, false];
    }
}
