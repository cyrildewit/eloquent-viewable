<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

/**
 * Everything the `retention` config asks for, in the order that loses nothing:
 * roll up, anonymise, then prune. One line in the scheduler.
 */
final class MaintainViewsCommand extends RetentionCommand
{
    #[\Override]
    protected $signature = 'views:maintain
        {--chunk= : How many views to change per statement}
        {--dry-run : Count the views that would change without changing them}';

    #[\Override]
    protected $description = 'Roll up, anonymise and delete old views as the retention config says';

    public function handle(AnonymiseViews $anonymise, PruneViews $prune, RetentionPolicy $policy, Config $config): int
    {
        $chunk = $this->chunk($policy);

        if ($chunk === null) {
            return self::FAILURE;
        }

        $rollups = $config->rollupTiers() !== [];
        $retains = $policy->anonymiseAfter instanceof Duration || $policy->pruneAfter instanceof Duration;

        if (! $rollups && ! $retains) {
            $this->components->info('Nothing to maintain, neither `retention.rollups.tiers`, `retention.anonymise.after` nor `retention.prune.after` is set.');

            return self::SUCCESS;
        }

        // Through the command, so retention stays unaware of how rollups are
        // folded. Rolling up first lets the cutoffs below move as far as the
        // rollups now reach.
        if ($rollups) {
            $status = $this->call('views:rollup', ['--chunk' => (string) $chunk, '--dry-run' => $this->isDryRun()]);

            if ($status !== self::SUCCESS) {
                return $status;
            }
        }

        if (! $retains) {
            return self::SUCCESS;
        }

        return $this->exclusively(function () use ($anonymise, $prune, $policy, $chunk): int {
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
}
