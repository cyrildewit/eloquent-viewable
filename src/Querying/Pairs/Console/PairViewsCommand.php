<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Querying\Pairs\Actions\PairViews;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\ReadsOptions;
use CyrildeWit\EloquentViewable\Support\Console\RunsScheduledWork;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

final class PairViewsCommand extends Command
{
    use ReadsOptions;
    use RunsScheduledWork;

    #[\Override]
    protected $signature = 'views:pairs
        {--period= : Pair only this one of the configured periods, such as 30d}';

    #[\Override]
    protected $description = 'Rewrite the pairs table that alsoViewed() and recommended() read';

    /**
     * @throws InvalidConfiguration
     * @throws InvalidOption
     */
    public function handle(PairViews $pair, Config $config, RunLock $lock): int
    {
        if (! $config->pairsEnabled()) {
            $this->components->info('Nothing to pair, `querying.pairs.enabled` is off.');

            return self::SUCCESS;
        }

        $periods = $this->periods($config->pairsPeriods());

        return $this->exclusively($lock, function () use ($pair, $periods): int {
            foreach ($periods as $period) {
                $this->components->info("Pairing the views of the last {$period->shorthand()}...");

                $paired = $pair->handle($period);

                $this->components->info("Paired {$paired->viewables} viewables into {$paired->pairs} pairs.");
            }

            return self::SUCCESS;
        });
    }

    /**
     * @param  non-empty-list<Duration>  $configured
     * @return non-empty-list<Duration>
     *
     * @throws InvalidOption
     */
    private function periods(array $configured): array
    {
        $only = $this->duration('period', null);

        if (! $only instanceof Duration) {
            return $configured;
        }

        $shorthands = array_map(static fn (Duration $period): string => $period->shorthand(), $configured);

        if (! in_array($only->shorthand(), $shorthands, true)) {
            throw InvalidOption::mustBeOneOf('period', $shorthands);
        }

        return [$only];
    }
}
