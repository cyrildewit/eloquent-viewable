<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Console;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
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
        {--from= : Fold again from this date instead of where the last run stopped}
        {--chunk= : How many expired rollup rows to delete per statement}
        {--dry-run : Count the buckets that would be folded without folding them}';

    #[\Override]
    protected $description = 'Fold the views of closed buckets into the rollup tiers and expire old tiers';

    public function handle(FoldViews $fold, ExpireTiers $expire, RollupPolicy $policy, Config $config, RunLock $lock): int
    {
        if (! $policy->isEnabled()) {
            $this->components->info('Nothing to roll up, `retention.rollups.tiers` is empty.');

            return self::SUCCESS;
        }

        $tier = $this->option('tier');
        $tier = is_string($tier) ? Tier::tryFrom($tier) ?? false : null;

        if ($tier === false || ($tier instanceof Tier && ! in_array($tier, $policy->tiers(), true))) {
            $this->components->error('The --tier option must name a configured tier: `'.implode('`, `', array_map(static fn (Tier $tier): string => $tier->value, $policy->tiers())).'`.');

            return self::FAILURE;
        }

        $from = $this->from();
        $chunk = $this->option('chunk') === null ? $config->retentionChunk() : filter_var($this->option('chunk'), FILTER_VALIDATE_INT);

        if ($from === false) {
            $this->components->error('The --from option must be a date such as `2025-01-01`.');

            return self::FAILURE;
        }

        if ($chunk === false || $chunk < 1) {
            $this->components->error('The --chunk option must be a positive integer.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $result = $lock->run(function () use ($fold, $expire, $tier, $from, $chunk, $dryRun): int {
            foreach ($fold->handle($tier, $from, $dryRun) as $run) {
                $buckets = "{$run->buckets} ".Str::plural('bucket', $run->buckets);

                $this->components->info($dryRun
                    ? "Would have folded {$buckets} of the {$run->tier->value} tier, up to {$run->until->toDateTimeString()}."
                    : "Folded {$buckets} of the {$run->tier->value} tier, up to {$run->until->toDateTimeString()}.");
            }

            foreach ($expire->handle($chunk, $dryRun) as $expired => $rows) {
                $this->components->info(($dryRun ? 'Would have dropped' : 'Dropped')." {$rows} expired ".Str::plural('row', $rows)." of the {$expired} tier.");
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
     * False once the option does not hold a date.
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
            return false;
        }
    }
}
