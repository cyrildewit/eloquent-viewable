<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\Console;

use CyrildeWit\EloquentViewable\Support\Deadline;
use Illuminate\Console\Command;

/**
 * Reads `--max-seconds`, how long a maintenance command keeps starting new
 * work before it stops and leaves the rest to its next run.
 *
 * @internal
 *
 * @phpstan-require-extends Command
 */
trait LimitsRunTime
{
    /**
     * Returns false when the option is not a positive integer. The error is
     * already written to the console, so the caller only has to stop.
     */
    protected function deadline(): Deadline|false
    {
        $option = $this->option('max-seconds');

        if ($option === null) {
            return Deadline::none();
        }

        $seconds = filter_var($option, FILTER_VALIDATE_INT);

        if ($seconds === false) {
            $this->components->error('The --max-seconds option must be a positive integer.');

            return false;
        }

        if ($seconds < 1) {
            $this->components->error('The --max-seconds option must be a positive integer.');

            return false;
        }

        return Deadline::in($seconds);
    }

    protected function reportStopped(): void
    {
        $this->components->warn('Stopped at the time limit. The next run carries on from here.');
    }
}
