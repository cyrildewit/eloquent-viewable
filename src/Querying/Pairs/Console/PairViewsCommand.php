<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Console;

use CyrildeWit\EloquentViewable\Querying\Pairs\Actions\PairViews;
use CyrildeWit\EloquentViewable\Querying\Pairs\Events\ViewsPaired;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

final class PairViewsCommand extends Command
{
    #[\Override]
    protected $signature = 'views:pairs';

    #[\Override]
    protected $description = 'Rewrite the pairs table that alsoViewed() and recommended() read';

    public function handle(PairViews $pair, Config $config, RunLock $lock): int
    {
        if (! $config->pairsEnabled()) {
            $this->components->info('Nothing to pair, `querying.pairs.enabled` is off.');

            return self::SUCCESS;
        }

        $this->components->info("Pairing the views of the last {$config->pairsPeriod()->shorthand()}...");

        $paired = $lock->run(static fn (): ViewsPaired => $pair->handle());

        if (! $paired instanceof ViewsPaired) {
            $this->components->warn('Another run is in progress, so this one was skipped.');

            return self::SUCCESS;
        }

        $this->components->info("Paired {$paired->viewables} viewables into {$paired->pairs} pairs.");

        return self::SUCCESS;
    }
}
