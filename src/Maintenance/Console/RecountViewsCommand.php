<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Maintenance\Console\Concerns\ReportsRecounts;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\MaintenanceCommand;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;

final class RecountViewsCommand extends MaintenanceCommand
{
    use ReportsRecounts;

    #[\Override]
    protected $signature = 'views:recount
        {--chunk= : How many models to recount per statement}
        {--max-seconds= : Stop starting new chunks after this many seconds, the next run carries on}
        {--full : Recount every model, not only those whose counts can have changed}';

    #[\Override]
    protected $description = 'Write the view counts of the configured counter columns';

    /**
     * @throws InvalidConfiguration
     * @throws InvalidOption
     */
    public function handle(RecountChangedViews $recount, Config $config, RunLock $lock): int
    {
        if ($config->counters() === []) {
            $this->components->info('Nothing to recount, `querying.counters` is empty.');

            return self::SUCCESS;
        }

        $chunk = $this->positiveInteger('chunk', $config->retentionChunk());
        $deadline = $this->deadline();

        return $this->exclusively($lock, function (Deadline $deadline) use ($recount, $chunk): int {
            $run = $recount->handle($chunk, $deadline, (bool) $this->option('full'));

            $this->reportRecounted($run);

            if ($run->stopped) {
                $this->reportStopped();
            }

            return self::SUCCESS;
        }, $deadline);
    }
}
