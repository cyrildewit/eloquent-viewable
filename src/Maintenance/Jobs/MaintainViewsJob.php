<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Jobs;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Maintenance\Actions\MaintainViews;
use CyrildeWit\EloquentViewable\Maintenance\Data\MaintenanceRun;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs what `views:maintain` runs, for at most `maxSeconds`. When there is
 * work left, the job queues itself again on the same connection and queue,
 * so a large backlog is worked through in short jobs that fit the time limit
 * of a worker or a serverless platform.
 *
 * Only one of these waits in the queue at a time, and a job that finds
 * another run holding the lock leaves the work to that run.
 */
final class MaintainViewsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $maxSeconds = 300,
        public ?int $chunk = null,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    public function handle(MaintainViews $maintain, Config $config, Dispatcher $bus): void
    {
        if (! $maintain->isConfigured()) {
            return;
        }

        $run = $maintain->handle($this->chunk ?? $config->retentionChunk(), Deadline::in($this->maxSeconds));

        if (! $run instanceof MaintenanceRun) {
            return;
        }

        if (! $run->stopped) {
            return;
        }

        $bus->dispatch(new self($this->maxSeconds, $this->chunk)->onConnection($this->connection)->onQueue($this->queue));
    }
}
