<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Recording\Jobs\FlushBufferedViewsJob;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Generator;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Reads the events the application schedules. A command run from a crontab
 * of its own is invisible here, so a missing one only warns.
 */
class ScheduleCheck implements Check
{
    /** @var list<string> */
    public const array MaintenanceCommands = ['views:maintain', 'views:rollup', 'views:anonymise', 'views:prune', 'views:recount'];

    public function __construct(
        protected Schedule $schedule,
        protected Config $config,
        protected RetentionPolicy $retention,
    ) {}

    public function name(): string
    {
        return 'Scheduler';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function run(): Generator
    {
        yield $this->maintenance();
        yield $this->flush();
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function maintenance(): Finding
    {
        if (! $this->needsMaintenance()) {
            return Finding::skipped('`views:maintain` has nothing to do: no rollups, retention or counter columns are configured.');
        }

        $event = $this->find(fn (Event $event): bool => $this->runsAnyOf($event, self::MaintenanceCommands));

        if (! $event instanceof Event) {
            return Finding::warning(
                '`views:maintain` is not scheduled, so rollups, retention and counter columns are not kept up to date.',
                "Add `Schedule::command('views:maintain')->hourly()->onOneServer();` to `routes/console.php`, unless a crontab of your own runs it.",
            );
        }

        if (! $event->onOneServer) {
            return Finding::advice(
                'Maintenance is scheduled, but not on one server, so every server runs it.',
                'Chain `->onOneServer()` onto the schedule.',
            );
        }

        return Finding::pass('Maintenance is scheduled on one server.');
    }

    /** @throws InvalidConfiguration */
    protected function flush(): Finding
    {
        if ($this->config->storeDriver() !== 'redis') {
            return Finding::skipped('`views:flush` has nothing to do: the `redis` store driver is not in use.');
        }

        $event = $this->find(fn (Event $event): bool => $this->runsAnyOf($event, ['views:flush']) || $event->description === FlushBufferedViewsJob::class);

        if (! $event instanceof Event) {
            return Finding::warning(
                '`views:flush` is not scheduled, so buffered views never reach the views table.',
                "Add `Schedule::command('views:flush')->everyMinute()->withoutOverlapping();` to `routes/console.php`, unless a crontab of your own runs it.",
            );
        }

        if (! $event->withoutOverlapping) {
            return Finding::advice(
                'The flush is scheduled, but may overlap a run that has not finished.',
                'Chain `->withoutOverlapping()` onto the schedule.',
            );
        }

        return Finding::pass('The flush is scheduled without overlapping.');
    }

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    protected function needsMaintenance(): bool
    {
        if ($this->config->rollupTiers() !== []) {
            return true;
        }

        if ($this->config->customRollups() !== []) {
            return true;
        }

        if ($this->config->counters() !== []) {
            return true;
        }

        if ($this->retention->anonymiseAfter instanceof Duration) {
            return true;
        }

        return $this->retention->pruneAfter instanceof Duration;
    }

    /** @param  callable(Event): bool  $matches */
    protected function find(callable $matches): ?Event
    {
        foreach ($this->schedule->events() as $event) {
            if ($matches($event)) {
                return $event;
            }
        }

        return null;
    }

    /** @param  list<string>  $commands */
    protected function runsAnyOf(Event $event, array $commands): bool
    {
        if (! is_string($event->command)) {
            return false;
        }

        foreach ($commands as $command) {
            $pattern = preg_quote($command, '/');

            if (preg_match("/(^|\\s){$pattern}(\\s|\$)/", $event->command) === 1) {
                return true;
            }
        }

        return false;
    }
}
