<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console\Concerns;

use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Support\Console\MaintenanceCommand;
use Illuminate\Support\Str;

/**
 * @internal
 *
 * @phpstan-require-extends MaintenanceCommand
 */
trait ReportsRetention
{
    protected function reportRetention(string $verb, RetentionRun $run): void
    {
        $noun = Str::plural('view', $run->views);
        $until = $run->until->toDateTimeString();
        $summary = "{$verb} {$run->views} {$noun} viewed before {$until}.";

        $this->components->info($run->dryRun ? "Would have {$summary}" : ucfirst($summary));

        if ($run->stopped) {
            $this->reportStopped();

            return;
        }

        if ($run->clamped) {
            $this->components->warn("Stopped at {$until}, because the rollups have not captured the views after it yet.");
        }
    }
}
