<?php

declare(strict_types=1);

/**
 * Seeds the benchmark dataset. Run through `make bench-seed`, or by hand:
 *
 *   composer bench:seed -- --size=medium --seed=42
 *
 * Replaces whatever dataset the connection holds.
 */

use CyrildeWit\EloquentViewable\Benchmarks\Support\Application;
use CyrildeWit\EloquentViewable\Benchmarks\Support\DatasetSize;
use CyrildeWit\EloquentViewable\Benchmarks\Support\Seeder;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$options = getopt('', ['size::', 'seed::']);

$size = DatasetSize::tryFrom((string) ($options['size'] ?? DatasetSize::Small->value))
    ?? throw new InvalidArgumentException(
        'Unknown size. Choose from: '.implode(', ', array_column(DatasetSize::cases(), 'value')).'.'
    );

$seed = (int) ($options['seed'] ?? 20_260_101);

Application::boot();

new Seeder(DB::connection(Application::Connection))->seed($size, $seed);
