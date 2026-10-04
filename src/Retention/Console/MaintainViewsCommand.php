<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

final class MaintainViewsCommand extends RetentionCommand
{
    #[\Override]
    protected $signature = 'views:maintain
        {--chunk= : How many views to change per statement}
        {--dry-run : Count the views that would change without changing them}';

    #[\Override]
    protected $description = 'Roll up, anonymise and delete old views and recount counter columns as configured';

    public function handle(AnonymiseViews $anonymise, PruneViews $prune, RetentionPolicy $policy, Config $config): int
    {
        $chunk = $this->chunk($policy);

        if ($chunk === null) {
            return self::FAILURE;
        }

        $rollups = $config->rollupTiers() !== [] || $config->customRollups() !== [];
        $counters = $config->counters() !== [];
        $retains = $policy->anonymiseAfter instanceof Duration || $policy->pruneAfter instanceof Duration;

        if (! $rollups && ! $counters && ! $retains) {
            $this->components->info('Nothing to maintain, neither `retention.rollups`, `retention.anonymise.after`, `retention.prune.after` nor `querying.counters` is set.');

            return self::SUCCESS;
        }

        if ($rollups) {
            $status = $this->rollUp($chunk);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        if ($retains) {
            $this->anonymiseAndPrune($anonymise, $prune, $policy, $chunk);
        }

        if ($counters) {
            return $this->recount($chunk);
        }

        return self::SUCCESS;
    }

    /**
     * Rollups are folded through their command, so retention does not depend on
     * how they are folded.
     */
    private function rollUp(int $chunk): int
    {
        return $this->call('views:rollup', ['--chunk' => (string) $chunk, '--dry-run' => $this->isDryRun()]);
    }

    private function anonymiseAndPrune(AnonymiseViews $anonymise, PruneViews $prune, RetentionPolicy $policy, int $chunk): void
    {
        $this->exclusively(function () use ($anonymise, $prune, $policy, $chunk): int {
            $now = Carbon::now();

            if ($policy->anonymiseAfter instanceof Duration) {
                $this->report('anonymised', $anonymise->handle($policy->anonymiseAfter->before($now), $policy->anonymiseColumns, $chunk, $this->isDryRun()));
            }

            if ($policy->pruneAfter instanceof Duration) {
                $this->report('deleted', $prune->handle($policy->pruneAfter->before($now), $chunk, $this->isDryRun()));
            }

            return self::SUCCESS;
        });
    }

    private function recount(int $chunk): int
    {
        if ($this->isDryRun()) {
            return self::SUCCESS;
        }

        return $this->call('views:recount', ['--chunk' => (string) $chunk]);
    }
}
