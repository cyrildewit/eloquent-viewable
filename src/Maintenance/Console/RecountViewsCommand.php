<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Console;

use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Maintenance\Console\Concerns\ReportsRecounts;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\LimitsRunTime;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

final class RecountViewsCommand extends Command
{
    use LimitsRunTime;
    use ReportsRecounts;

    #[\Override]
    protected $signature = 'views:recount
        {--chunk= : How many models to recount per statement}
        {--max-seconds= : Stop starting new chunks after this many seconds, the next run carries on}
        {--full : Recount every model, not only those whose counts can have changed}';

    #[\Override]
    protected $description = 'Write the view counts of the configured counter columns';

    public function handle(RecountChangedViews $recount, Config $config, RunLock $lock): int
    {
        if ($config->counters() === []) {
            $this->components->info('Nothing to recount, `querying.counters` is empty.');

            return self::SUCCESS;
        }

        $chunk = $this->chunk($config);
        $deadline = $this->deadline();

        if ($chunk === null) {
            return self::FAILURE;
        }

        if ($deadline === false) {
            return self::FAILURE;
        }

        $result = $lock->run(function (Deadline $deadline) use ($recount, $chunk): int {
            $run = $recount->handle($chunk, $deadline, (bool) $this->option('full'));

            $this->reportRecounted($run);

            if ($run->stopped) {
                $this->reportStopped();
            }

            return self::SUCCESS;
        }, $deadline);

        if ($result === null) {
            $this->components->warn('Another run is in progress, so this one was skipped.');

            return self::SUCCESS;
        }

        return $result;
    }

    /**
     * It returns null once the error is reported.
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
