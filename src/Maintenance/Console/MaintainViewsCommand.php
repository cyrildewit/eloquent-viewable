<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Console;

use CyrildeWit\EloquentViewable\Maintenance\Actions\MaintainViews;
use CyrildeWit\EloquentViewable\Maintenance\Console\Concerns\ReportsRecounts;
use CyrildeWit\EloquentViewable\Maintenance\Data\MaintenanceRun;
use CyrildeWit\EloquentViewable\Maintenance\Data\RecountRun;
use CyrildeWit\EloquentViewable\Querying\Rollups\Console\Concerns\ReportsRollups;
use CyrildeWit\EloquentViewable\Retention\Console\RetentionCommand;
use CyrildeWit\EloquentViewable\Retention\Data\RetentionRun;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;

final class MaintainViewsCommand extends RetentionCommand
{
    use ReportsRecounts;
    use ReportsRollups;

    #[\Override]
    protected $signature = 'views:maintain
        {--chunk= : How many views to change per statement}
        {--max-seconds= : Stop starting new work after this many seconds, the next run carries on}
        {--dry-run : Count the views that would change without changing them}';

    #[\Override]
    protected $description = 'Roll up, anonymise and delete old views and recount counter columns as configured';

    public function handle(MaintainViews $maintain, RetentionPolicy $policy): int
    {
        $chunk = $this->chunk($policy);
        $deadline = $this->deadline();

        if ($chunk === null) {
            return self::FAILURE;
        }

        if ($deadline === false) {
            return self::FAILURE;
        }

        if (! $maintain->isConfigured()) {
            $this->components->info('Nothing to maintain, neither `retention.rollups`, `retention.anonymise.after`, `retention.prune.after` nor `querying.counters` is set.');

            return self::SUCCESS;
        }

        $run = $maintain->handle($chunk, $deadline, $this->isDryRun());

        if (! $run instanceof MaintenanceRun) {
            $this->components->warn('Another run is in progress, so this one was skipped.');

            return self::SUCCESS;
        }

        $this->reportRun($run);

        return self::SUCCESS;
    }

    private function reportRun(MaintenanceRun $run): void
    {
        foreach ($run->folded as $folded) {
            $this->reportFolded($folded, $this->isDryRun());
        }

        foreach ($run->expired as $expired) {
            $this->reportExpired($expired['rollup'], $expired['tier'], $expired['rows'], $this->isDryRun());
        }

        if ($run->anonymised instanceof RetentionRun) {
            $this->report('anonymised', $run->anonymised);
        }

        if ($run->pruned instanceof RetentionRun) {
            $this->report('deleted', $run->pruned);
        }

        if ($run->recounted instanceof RecountRun) {
            $this->reportRecounted($run->recounted);
        }

        if (! $run->stopped) {
            return;
        }

        if ($this->retentionReportedStop($run)) {
            return;
        }

        $this->reportStopped();
    }

    /**
     * A retention step that stopped has said so in its own report already.
     */
    private function retentionReportedStop(MaintenanceRun $run): bool
    {
        if ($run->anonymised?->stopped === true) {
            return true;
        }

        return $run->pruned?->stopped === true;
    }
}
