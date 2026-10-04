<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

final class PruneViewsCommand extends RetentionCommand
{
    #[\Override]
    protected $signature = 'views:prune
        {--older-than= : Delete views older than this, such as 90d, instead of retention.prune.after}
        {--chunk= : How many views to delete per statement}
        {--dry-run : Count the views that would be deleted without deleting them}';

    #[\Override]
    protected $description = 'Delete old views';

    public function handle(PruneViews $prune, RetentionPolicy $policy): int
    {
        $chunk = $this->chunk($policy);
        $after = $this->olderThan($policy->pruneAfter);

        if ($chunk === null || $after === false) {
            return self::FAILURE;
        }

        if (! $after instanceof Duration) {
            $this->components->info('Nothing to prune, `retention.prune.after` is not set.');

            return self::SUCCESS;
        }

        return $this->exclusively(function () use ($prune, $after, $chunk): int {
            $this->report('deleted', $prune->handle($after->before(Carbon::now()), $chunk, $this->isDryRun()));

            return self::SUCCESS;
        });
    }
}
