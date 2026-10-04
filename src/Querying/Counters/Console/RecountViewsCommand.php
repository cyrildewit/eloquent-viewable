<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters\Console;

use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RecountViewsCommand extends Command
{
    #[\Override]
    protected $signature = 'views:recount {--chunk= : How many models to recount per statement}';

    #[\Override]
    protected $description = 'Write the view counts of the configured counter columns';

    public function handle(RecountViews $recount, Config $config): int
    {
        if ($config->counters() === []) {
            $this->components->info('Nothing to recount, `querying.counters` is empty.');

            return self::SUCCESS;
        }

        $chunk = $this->chunk($config);

        if ($chunk === null) {
            return self::FAILURE;
        }

        foreach ($recount->handle($chunk) as $class => $models) {
            $noun = Str::plural(class_basename($class), $models);

            $this->components->info("Recounted {$models} {$noun}.");
        }

        return self::SUCCESS;
    }

    /**
     * It returns the chunk size, or null once the error is reported.
     */
    private function chunk(Config $config): ?int
    {
        $option = $this->option('chunk');

        if ($option === null) {
            return $config->retentionChunk();
        }

        $chunk = filter_var($option, FILTER_VALIDATE_INT);

        if ($chunk === false) {
            $this->components->error('The --chunk option must be a positive integer.');

            return null;
        }

        if ($chunk < 1) {
            $this->components->error('The --chunk option must be a positive integer.');

            return null;
        }

        return $chunk;
    }
}
