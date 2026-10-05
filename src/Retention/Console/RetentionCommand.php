<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** @internal */
abstract class RetentionCommand extends Command
{
    /**
     * It returns null once the error is reported.
     */
    protected function chunk(RetentionPolicy $policy): ?int
    {
        $chunk = $this->positiveInteger('chunk', $policy->chunk);

        return $chunk === false ? null : $chunk;
    }

    /**
     * It returns false once the error is reported.
     */
    protected function positiveInteger(string $name, int $default): int|false
    {
        $option = $this->option($name);

        if ($option === null) {
            return $default;
        }

        $value = filter_var($option, FILTER_VALIDATE_INT);

        if ($value === false) {
            $this->components->error("The --{$name} option must be a positive integer.");

            return false;
        }

        if ($value < 1) {
            $this->components->error("The --{$name} option must be a positive integer.");

            return false;
        }

        return $value;
    }

    /**
     * It returns false once the error is reported.
     */
    protected function olderThan(?Duration $configured): Duration|false|null
    {
        $option = $this->option('older-than');

        if (! is_string($option)) {
            return $configured;
        }

        $duration = Duration::tryParse($option);

        if (! $duration instanceof Duration) {
            $this->components->error('The --older-than option must be a duration such as `30d` or `2y`.');

            return false;
        }

        return $duration;
    }

    protected function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    /**
     * @param  Closure(): int  $callback
     *
     * @throws InvalidConfiguration
     * @throws LockUnavailable
     */
    protected function exclusively(Closure $callback): int
    {
        $result = $this->laravel->make(RunLock::class)->run($callback);

        if ($result === null) {
            $this->components->warn('Another run is in progress, so this one was skipped.');

            return self::SUCCESS;
        }

        return $result;
    }

    protected function report(string $verb, RetentionRun $run): void
    {
        $noun = Str::plural('view', $run->views);
        $until = $run->until->toDateTimeString();
        $summary = "{$verb} {$run->views} {$noun} viewed before {$until}.";

        $this->components->info($run->dryRun ? "Would have {$summary}" : ucfirst($summary));

        if ($run->clamped) {
            $this->components->warn("Stopped at {$until}, because the rollups have not captured the views after it yet.");
        }
    }
}
