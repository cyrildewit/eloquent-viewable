<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\Console;

use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

/**
 * Runs a command's work under the one lock, and says when a run was skipped
 * because another holds the lock or stopped at its deadline.
 *
 * @internal
 *
 * @phpstan-require-extends Command
 */
trait RunsScheduledWork
{
    /**
     * @param  Closure(Deadline): int  $callback
     *
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    protected function exclusively(RunLock $lock, Closure $callback, ?Deadline $deadline = null): int
    {
        $result = $lock->run($callback, $deadline);

        if ($result === null) {
            $this->reportSkipped();

            return self::SUCCESS;
        }

        return $result;
    }

    protected function reportSkipped(): void
    {
        $this->components->warn('Another run is in progress, so this one was skipped.');
    }

    protected function reportStopped(): void
    {
        $this->components->warn('Stopped at the time limit. The next run carries on from here.');
    }
}
