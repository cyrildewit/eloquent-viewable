<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Console;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupDefinition;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class RollupViewsCommand extends Command
{
    #[\Override]
    protected $signature = 'views:rollup
        {--tier= : Fold only this tier: hour, day, month or year}
        {--rollup= : Fold only the rollup of this name, views for the built-in one}
        {--from= : Fold again from this date instead of where the last run stopped}
        {--chunk= : How many expired rollup rows to delete per statement}
        {--dry-run : Count the buckets that would be folded without folding them}';

    #[\Override]
    protected $description = 'Fold the views of closed buckets into the rollup tiers and expire old tiers';

    public function handle(FoldViews $fold, ExpireTiers $expire, RollupPolicy $policy, Config $config, RunLock $lock): int
    {
        if (! $policy->isEnabled()) {
            $this->components->info('Nothing to roll up, neither `retention.rollups.tiers` nor `retention.rollups.custom` is set.');

            return self::SUCCESS;
        }

        $rollup = $this->rollup($policy);

        if ($rollup === false) {
            return self::FAILURE;
        }

        $tier = $this->tier($policy, $rollup);
        $from = $this->from();
        $chunk = $this->chunk($config);

        if ($tier === false) {
            return self::FAILURE;
        }

        if ($from === false) {
            return self::FAILURE;
        }

        if ($chunk === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $result = $lock->run(function () use ($fold, $expire, $tier, $from, $chunk, $dryRun, $rollup): int {
            foreach ($fold->handle($tier, $from, $dryRun, $rollup?->name) as $run) {
                $this->reportFolded($run, $dryRun);
            }

            foreach ($expire->handle($chunk, $dryRun) as $expired) {
                $this->reportExpired($expired['rollup'], $expired['tier'], $expired['rows'], $dryRun);
            }

            return self::SUCCESS;
        });

        if ($result === null) {
            $this->components->warn('Another run is in progress, so this one was skipped.');

            return self::SUCCESS;
        }

        return $result;
    }

    /**
     * It returns false once the error is reported.
     */
    private function rollup(RollupPolicy $policy): RollupDefinition|false|null
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

        $this->components->error("The --rollup option must name a configured rollup: `{$names}`.");

        return false;
    }

    /**
     * It returns false once the error is reported.
     */
    private function tier(RollupPolicy $policy, ?RollupDefinition $rollup): Tier|false|null
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

        $this->components->error("The --tier option must name a configured tier: `{$names}`.");

        return false;
    }

    /**
     * It returns null once the error is reported.
     */
    private function chunk(Config $config): ?int
    {
        $option = $this->option('chunk');

        if ($option === null) {
            return $config->retentionChunk();
        }

        $chunk = filter_var($option, FILTER_VALIDATE_INT);

        if ($chunk === false) {
            $this->components->error('The --chunk option must be a positive integer.');

            return null;
        }

        if ($chunk < 1) {
            $this->components->error('The --chunk option must be a positive integer.');

            return null;
        }

        return $chunk;
    }

    private function reportFolded(ViewsRolledUp $run, bool $dryRun): void
    {
        $buckets = Str::plural('bucket', $run->buckets);
        $summary = "folded {$run->buckets} {$buckets} of {$this->tierOf($run->rollup, $run->tier)}, up to {$run->until->toDateTimeString()}.";

        if ($dryRun) {
            $this->components->info("Would have {$summary}");

            return;
        }

        $this->components->info(ucfirst($summary));
    }

    private function reportExpired(string $rollup, Tier $tier, int $rows, bool $dryRun): void
    {
        $noun = Str::plural('row', $rows);
        $summary = "dropped {$rows} expired {$noun} of {$this->tierOf($rollup, $tier)}.";

        if ($dryRun) {
            $this->components->info("Would have {$summary}");

            return;
        }

        $this->components->info(ucfirst($summary));
    }

    private function tierOf(string $rollup, Tier $tier): string
    {
        if ($rollup === RollupPolicy::BuiltIn) {
            return "the {$tier->value} tier";
        }

        return "the {$tier->value} tier of `{$rollup}`";
    }

    /**
     * It returns false once the error is reported.
     */
    private function from(): CarbonImmutable|false|null
    {
        $from = $this->option('from');

        if (! is_string($from)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($from);
        } catch (InvalidFormatException) {
            $this->components->error('The --from option must be a date such as `2025-01-01`.');

            return false;
        }
    }
}
