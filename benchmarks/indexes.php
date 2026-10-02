<?php

declare(strict_types=1);

/**
 * Brings the optional indexes on the seeded dataset in line with `--set`.
 * Run through `make bench-indexes`, or by hand:
 *
 *   composer bench:indexes -- --set=visitor,type-viewed-at
 *   composer bench:indexes -- --set=none
 *
 * Indexes in the set that are missing are created, indexes that are present
 * but not in the set are dropped, and the dataset description records the
 * result so a run knows which variant it measured.
 */

use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use CyrildeWit\EloquentViewable\Benchmarks\Support\OptionalIndex;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Output;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['set:']);

if (! isset($options['set']) || ! is_string($options['set'])) {
    throw new InvalidArgumentException(
        'Pass --set with a comma-separated list of indexes ('.implode(', ', array_column(OptionalIndex::cases(), 'value')).') or none.'
    );
}

$wanted = OptionalIndex::fromList($options['set']);

$app = Application::boot();
$connection = DB::connection(Application::CONNECTION);
$table = $app->make(Config::class)->viewTable() ?? 'views';

$dataset = Dataset::load($connection);

Output::heading("Optional indexes on {$connection->getDriverName()}");

foreach (OptionalIndex::cases() as $index) {
    $has = in_array($index, $dataset->indexes, true);
    $want = in_array($index, $wanted, true);

    if ($has === $want) {
        Output::line(sprintf('%-15s %s', $index->value, $has ? 'present' : 'absent'));

        continue;
    }

    $startedAt = microtime(true);

    $want ? $index->create($connection, $table) : $index->drop($connection, $table);

    Output::line(sprintf('%-15s %s in %s', $index->value, $want ? 'created' : 'dropped', Output::elapsed($startedAt)));
}

$dataset->withIndexes($wanted)->save($connection);

Output::line();
Output::line($dataset->withIndexes($wanted)->describe());
