<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Console\Concerns\ReportsRollups;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\ReadsOptions;
use CyrildeWit\EloquentViewable\Support\Console\RunsScheduledWork;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;

final class RollupViewsCommand extends Command
{
    use ReadsOptions;
    use ReportsRollups;
    use RunsScheduledWork;

    #[\Override]
    protected $signature = 'views:rollup
        {--tier= : Fold only this tier: hour, day, month or year}
        {--rollup= : Fold only the rollup of this name, views for the built-in one}
        {--from= : Fold again from this date instead of where the last run stopped}
        {--chunk= : How many expired rollup rows to delete per statement}
        {--max-seconds= : Stop starting new buckets after this many seconds, the next run carries on}
        {--dry-run : Count the buckets that would be folded without folding them}';

    #[\Override]
    protected $description = 'Fold the views of closed buckets into the rollup tiers and expire old tiers';

    /**
     * @throws InvalidConfiguration
     * @throws InvalidOption
     */
    public function handle(FoldViews $fold, ExpireTiers $expire, RollupPolicy $policy, Config $config, RunLock $lock): int
    {
        if (! $policy->isEnabled()) {
            $this->components->info('Nothing to roll up, neither `retention.rollups.tiers` nor `retention.rollups.custom` is set.');

            return self::SUCCESS;
        }

        $rollup = $this->rollup($policy);
        $tier = $this->tier($policy, $rollup);
        $from = $this->date('from', '2025-01-01');
        $chunk = $this->positiveInteger('chunk', $config->retentionChunk());
        $deadline = $this->deadline();
        $dryRun = $this->isDryRun();

        return $this->exclusively($lock, function (Deadline $deadline) use ($fold, $expire, $tier, $from, $chunk, $dryRun, $rollup): int {
            foreach ($fold->handle($tier, $from, $dryRun, $rollup?->name, $deadline) as $run) {
                $this->reportFolded($run, $dryRun);

                if ($run->stopped) {
                    $this->reportStopped();

                    return self::SUCCESS;
                }
            }

            foreach ($expire->handle($chunk, $dryRun, $deadline) as $expired) {
                $this->reportExpired($expired['rollup'], $expired['tier'], $expired['rows'], $dryRun);

                if ($expired['stopped']) {
                    $this->reportStopped();
                }
            }

            return self::SUCCESS;
        }, $deadline);
    }

    /** @throws InvalidOption */
    private function rollup(RollupPolicy $policy): ?RollupDefinition
    {
        $name = $this->option('rollup');

        if (! is_string($name)) {
            return null;
        }

        $rollup = $policy->find($name);

        if ($rollup instanceof RollupDefinition) {
            return $rollup;
        }

        $names = implode('`, `', array_map(static fn (RollupDefinition $definition): string => $definition->name, $policy->definitions()));

        throw new InvalidOption("The --rollup option must name a configured rollup: `{$names}`.");
    }

    /** @throws InvalidOption */
    private function tier(RollupPolicy $policy, ?RollupDefinition $rollup): ?Tier
    {
        $name = $this->option('tier');

        if (! is_string($name)) {
            return null;
        }

        $tiers = [];

        foreach ($rollup instanceof RollupDefinition ? [$rollup] : $policy->definitions() as $definition) {
            $tiers = [...$tiers, ...array_map(static fn (Tier $tier): string => $tier->value, $definition->tiers())];
        }

        $tiers = array_values(array_unique($tiers));

        if (in_array($name, $tiers, true)) {
            return Tier::from($name);
        }

        $names = implode('`, `', $tiers);

        throw new InvalidOption("The --tier option must name a configured tier: `{$names}`.");
    }
}
