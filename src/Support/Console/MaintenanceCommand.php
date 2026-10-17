<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\Console;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Exceptions\LockUnavailable;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The base of the commands that run the package's scheduled work. It reads
 * the options they share, runs them under the one lock, and reports an
 * option the command cannot run with as an error that fails the command.
 *
 * @internal
 */
abstract class MaintenanceCommand extends Command
{
    #[\Override]
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (InvalidOption $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @throws InvalidOption */
    protected function positiveInteger(string $name, int $default): int
    {
        return $this->integer($name) ?? $default;
    }

    /** @throws InvalidOption */
    protected function duration(string $name, ?Duration $default): ?Duration
    {
        $option = $this->option($name);

        if (! is_string($option)) {
            return $default;
        }

        return Duration::tryParse($option) ?? throw InvalidOption::mustBeDuration($name);
    }

    /** @throws InvalidOption */
    protected function date(string $name, string $example): ?CarbonImmutable
    {
        $option = $this->option($name);

        if (! is_string($option)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($option);
        } catch (InvalidFormatException) {
            throw InvalidOption::mustBeDate($name, $example);
        }
    }

    /**
     * How long the command keeps starting new work before it stops and
     * leaves the rest to its next run, from `--max-seconds`.
     *
     * @throws InvalidOption
     */
    protected function deadline(): Deadline
    {
        $seconds = $this->integer('max-seconds');

        return $seconds === null ? Deadline::none() : Deadline::in($seconds);
    }

    protected function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

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

    /** @throws InvalidOption */
    private function integer(string $name): ?int
    {
        $option = $this->option($name);

        if ($option === null) {
            return null;
        }

        $value = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? throw InvalidOption::mustBePositiveInteger($name) : $value;
    }
}
