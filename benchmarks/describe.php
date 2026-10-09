<?php

declare(strict_types=1);

/**
 * Prints the seeded dataset, the database it lives in and the installed
 * Laravel version as JSON. Run through `make bench-describe`, or by hand:
 *
 *   composer bench:describe
 *   composer bench:describe -- --output=build/dataset.json
 *
 * The results repository records it next to every run, so a number is always
 * read together with the data it was measured on. Laravel is included because
 * composer.lock is not committed: a release measured later installs a newer
 * framework than it shipped with, and the framework shapes the queries.
 * `--output` writes a file instead, which keeps the JSON clean of anything
 * else on standard output.
 */

use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Dataset;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['output:']);

$app = Application::boot();

$connection = DB::connection(Application::Connection);

if (! $connection instanceof Connection) {
    throw new RuntimeException('The benchmark connection does not report its server version.');
}

$json = json_encode([
    ...Dataset::load($connection)->toArray(),
    'database' => [
        'driver' => $connection->getDriverName(),
        'server_version' => $connection->getServerVersion(),
    ],
    'laravel' => $app->version(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

$output = $options['output'] ?? null;

if (! is_string($output)) {
    fwrite(STDOUT, $json);

    return;
}

if (@file_put_contents($output, $json) === false) {
    throw new RuntimeException("Could not write {$output}, does its directory exist?");
}
