<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Querying\Pairs\Actions\PairViews;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\RunsScheduledWork;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

final class PairViewsCommand extends Command
{
    use RunsScheduledWork;

    #[\Override]
    protected $signature = 'views:pairs';

    #[\Override]
    protected $description = 'Rewrite the pairs table that alsoViewed() and recommended() read';

    /** @throws InvalidConfiguration */
    public function handle(PairViews $pair, Config $config, RunLock $lock): int
    {
        if (! $config->pairsEnabled()) {
            $this->components->info('Nothing to pair, `querying.pairs.enabled` is off.');

            return self::SUCCESS;
        }

        $this->components->info("Pairing the views of the last {$config->pairsPeriod()->shorthand()}...");

        return $this->exclusively($lock, function () use ($pair): int {
            $paired = $pair->handle();

            $this->components->info("Paired {$paired->viewables} viewables into {$paired->pairs} pairs.");

            return self::SUCCESS;
        });
    }
}
