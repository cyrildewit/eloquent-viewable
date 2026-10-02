<?php

declare(strict_types=1);

/**
 * Prints the SQL and the query plan of every read path the benchmarks time,
 * on the current driver. Plans are deterministic where timings are noisy, so
 * a lost index shows up here before it shows up as a slower number. Run
 * through `make bench-explain`, or by hand:
 *
 *   composer bench:explain
 *   composer bench:explain -- --analyze   # execute the queries and show actual rows and time
 */

use CyrildeWit\EloquentViewable\Benchmarks\Models\Article;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Output;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Granularity;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$analyze = isset(getopt('', ['analyze'])['analyze']);

Application::boot();

/** @var Connection $connection */
$connection = DB::connection(Application::CONNECTION);
$driver = $connection->getDriverName();
$dataset = Dataset::load($connection);

$hot = $dataset->hotArticle();

$cases = [
    'count, hot article, all time' => fn (): int => views($hot)->count(),
    'count, hot article, past 30 days' => fn (): int => views($hot)->period($dataset->pastDays(30))->count(),
    'unique count, hot article, past 30 days' => fn (): int => views($hot)->period($dataset->pastDays(30))->unique()->count(),
    'count, all articles, past 30 days' => fn (): int => views(Article::class)->period($dataset->pastDays(30))->count(),
    'count by day, hot article, past year' => fn (): ViewSeries => views($hot)->period($dataset->pastDays(365))->countByInterval(Granularity::Day),
    'unique count by hour, hot article, past 7 days' => fn (): ViewSeries => views($hot)->period($dataset->pastDays(7))->unique()->countByInterval(Granularity::Hour),
    'count by day, all articles, past year' => fn (): ViewSeries => views(Article::class)->period($dataset->pastDays(365))->countByInterval(Granularity::Day),
    'order by views, all time, first page' => fn () => Article::query()->orderByViews()->limit(20)->get(),
    'order by unique views, past 30 days, first page' => fn () => Article::query()->orderByUniqueViews('desc', $dataset->pastDays(30))->limit(20)->get(),
];

/**
 * The statement that explains a query on this driver.
 */
$explain = (static fn (string $sql): string => match ($driver) {
    'sqlite' => "explain query plan {$sql}",
    'pgsql' => $analyze ? "explain (analyze, buffers) {$sql}" : "explain {$sql}",
    'mysql' => $analyze ? "explain analyze {$sql}" : "explain {$sql}",
    'mariadb' => $analyze ? "analyze {$sql}" : "explain {$sql}",
    default => throw new RuntimeException("No explain statement for the [{$driver}] driver."),
});

Output::line($dataset->describe());
Output::line("Driver: {$driver}".($analyze ? ', executing the queries' : ''));

foreach ($cases as $title => $case) {
    Output::heading($title);

    foreach ($connection->pretend($case) as $query) {
        $sql = $connection->getQueryGrammar()->substituteBindingsIntoRawSql(
            $query['query'],
            $connection->prepareBindings($query['bindings']),
        );

        Output::line($sql);
        Output::line();

        foreach ($connection->select($explain($sql)) as $row) {
            $columns = (array) $row;

            // Postgres and MySQL's analyze return one text column per line;
            // the others return a row of named columns.
            Output::line(count($columns) === 1
                ? '  '.reset($columns)
                : '  '.implode('  ', array_map(
                    static fn (string $column, mixed $value): string => "{$column}=".($value ?? 'null'),
                    array_keys($columns),
                    $columns,
                )));
        }
    }
}
