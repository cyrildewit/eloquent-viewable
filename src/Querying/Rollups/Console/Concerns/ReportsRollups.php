<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Console\Concerns;

use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * @internal
 *
 * @phpstan-require-extends Command
 */
trait ReportsRollups
{
    protected function reportFolded(ViewsRolledUp $run, bool $dryRun): void
    {
        $buckets = Str::plural('bucket', $run->buckets);
        $summary = "folded {$run->buckets} {$buckets} of {$this->tierOf($run->rollup, $run->tier)}, up to {$run->until->toDateTimeString()}.";

        if ($dryRun) {
            $this->components->info("Would have {$summary}");

            return;
        }

        $this->components->info(ucfirst($summary));
    }

    protected function reportExpired(string $rollup, Tier $tier, int $rows, bool $dryRun): void
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
}
