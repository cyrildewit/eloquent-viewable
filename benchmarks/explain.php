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
 *
 * The cases are discovered from the benchmark classes, so they are named
 * exactly as phpbench names them in its dump and cannot drift from it. The
 * results repository stores the JSON next to every run and shows the SQL on
 * each benchmark's page.
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

$options = getopt('', ['analyze', 'group:', 'output:']);

$analyze = isset($options['analyze']);
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

$report = new QueryReport($driver, $analyze, $group);

Output::line($dataset->describe());
Output::line("Driver: {$driver}, group: {$group}".($analyze ? ', executing the queries' : ''));

foreach ($benchmarks as $benchmark) {
    $instance = new ($benchmark->class)();

    foreach ($benchmark->beforeMethods as $method) {
        $instance->{$method}();
    }

    foreach ($benchmark->variants as $variant) {
        Output::heading($variant->title());

        $queries = [];
        $captured = $connection->pretend(static fn () => $instance->{$variant->subject}($variant->params));

        foreach ($captured as $query) {
            $sql = $connection->getQueryGrammar()->substituteBindingsIntoRawSql(
                $query['query'],
                $connection->prepareBindings($query['bindings']),
            );
            $plan = QueryReport::plan($connection->select($explain($sql)));

            Output::line($sql);
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

        $report->add($variant, $queries);
    }
}

if ($output !== null) {
    if (@file_put_contents($output, $report->toJson()) === false) {
        throw new RuntimeException("Could not write {$output}, does its directory exist?");
    }

    Output::line();
    Output::line("Written to {$output}.");
}
