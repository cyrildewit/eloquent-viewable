<?php

declare(strict_types=1);

/**
 * Prints the SQL and the query plan of every variant of the read benchmarks,
 * on the current driver. Plans are deterministic where timings are noisy, so
 * a lost index shows up here before it shows up as a slower number. Run
 * through `make bench-explain`, or by hand:
 *
 *   composer bench:explain
 *   composer bench:explain -- --analyze                    # execute the queries and show actual rows and time
 *   composer bench:explain -- --group=write                # another phpbench group
 *   composer bench:explain -- --output=build/queries.json  # write the report as JSON as well
 *   composer bench:explain -- --execute                    # run each subject to capture every query it makes
 *   composer bench:explain -- --filter=Recommended         # only the variants whose title matches
 *
 * The cases are discovered from the benchmark classes, so they are named
 * exactly as phpbench names them in its dump and cannot drift from it. The
 * results repository stores the JSON next to every run and shows the SQL on
 * each benchmark's page.
 *
 * By default each subject runs under `pretend()`, which returns no rows, so a
 * subject whose later queries depend on the rows of an earlier one only shows
 * the first, as `recommended()` does. `--execute` runs each subject for real
 * inside a transaction that is rolled back, and explains every query it made.
 *
 * Each class's before-methods run once, without parameters and outside
 * `pretend()`, which is what the read benchmarks' `setUp` needs: it boots the
 * application and loads the dataset. A before-method that writes to the
 * database would write here too.
 */

use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Output;
use CyrildeWit\EloquentViewable\Benchmarks\Support\QueryReport;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Variants;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['analyze', 'execute', 'filter:', 'group:', 'output:']);

$analyze = isset($options['analyze']);
$execute = isset($options['execute']);
$filter = is_string($options['filter'] ?? null) ? $options['filter'] : null;
$group = is_string($options['group'] ?? null) ? $options['group'] : 'read';
$output = is_string($options['output'] ?? null) ? $options['output'] : null;

Application::boot();

$connection = DB::connection(Application::Connection);

if (! $connection instanceof Connection) {
    throw new RuntimeException('The benchmark connection cannot pretend to run queries.');
}

$driver = $connection->getDriverName();
$dataset = Dataset::load($connection);
$benchmarks = Variants::discover()->inGroup($group);

if ($benchmarks === []) {
    throw new RuntimeException("No benchmark carries the [{$group}] group.");
}

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

/**
 * Run the callback for real and roll back whatever it wrote, returning every
 * query it made in the shape `pretend()` returns.
 *
 * @return list<array{query: string, bindings: array<mixed>, time: float}>
 */
function capture(Connection $connection, Closure $callback): array
{
    $queries = [];

    $connection->enableQueryLog();
    $connection->flushQueryLog();
    $connection->beginTransaction();

    try {
        $callback();
    } finally {
        $connection->rollBack();

        foreach ($connection->getQueryLog() as $query) {
            $queries[] = ['query' => $query['query'], 'bindings' => $query['bindings'], 'time' => (float) $query['time']];
        }

        $connection->disableQueryLog();
    }

    return $queries;
}

$report = new QueryReport($driver, $analyze, $group, $execute);

Output::line($dataset->describe());
Output::line("Driver: {$driver}, group: {$group}".($analyze ? ', executing the queries' : '').($execute ? ', running the subjects' : ''));

foreach ($benchmarks as $benchmark) {
    $instance = new ($benchmark->class)();

    foreach ($benchmark->beforeMethods as $method) {
        $instance->{$method}();
    }

    foreach ($benchmark->variants as $variant) {
        if ($filter !== null && ! str_contains($variant->title(), $filter)) {
            continue;
        }

        Output::heading($variant->title());

        $queries = [];
        $timings = [];
        $captured = $execute
            ? capture($connection, static fn () => $instance->{$variant->subject}($variant->params))
            : $connection->pretend(static fn () => $instance->{$variant->subject}($variant->params));

        foreach ($captured as $query) {
            $sql = $connection->getQueryGrammar()->substituteBindingsIntoRawSql(
                $query['query'],
                $connection->prepareBindings($query['bindings']),
            );
            $plan = QueryReport::plan($connection->select($explain($sql)));

            Output::line($sql);

            if (isset($query['time'])) {
                Output::line("  ran in {$query['time']} ms");

                $timings[] = $query['time'];
            }

            Output::line();

            foreach ($plan['rows'] as $row) {
                // Postgres and MySQL's analyze return one text column per line;
                // the others return a row of named columns.
                Output::line(count($row) === 1
                    ? '  '.$row[0]
                    : '  '.implode('  ', array_map(
                        static fn (string $column, ?string $value): string => "{$column}=".($value ?? 'null'),
                        $plan['columns'],
                        $row,
                    )));
            }

            $queries[] = ['sql' => $sql, 'plan' => $plan];
        }

        if ($queries === []) {
            Output::line('No queries.');
        }

        $report->add($variant, $queries, $timings);
    }
}

if ($output !== null) {
    if (@file_put_contents($output, $report->toJson()) === false) {
        throw new RuntimeException("Could not write {$output}, does its directory exist?");
    }

    Output::line();
    Output::line("Written to {$output}.");
}
