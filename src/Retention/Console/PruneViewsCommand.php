<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

final class PruneViewsCommand extends RetentionCommand
{
    #[\Override]
    protected $signature = 'views:prune
        {--older-than= : Delete views older than this, such as 90d, instead of retention.prune.after}
        {--before= : Delete views viewed before this date, such as the start of a dropped partition}
        {--chunk= : How many views to delete per statement}
        {--max-seconds= : Stop starting new chunks after this many seconds, the next run carries on}
        {--dry-run : Count the views that would be deleted without deleting them}';

    #[\Override]
    protected $description = 'Delete old views';

    public function handle(PruneViews $prune, RetentionPolicy $policy): int
    {
        $chunk = $this->chunk($policy);
        $after = $this->olderThan($policy->pruneAfter);
        $before = $this->before();
        $deadline = $this->deadline();

        if ($chunk === null) {
            return self::FAILURE;
        }

        if ($deadline === false) {
            return self::FAILURE;
        }

        if ($after === false) {
            return self::FAILURE;
        }

        if ($before === false) {
            return self::FAILURE;
        }

        $cutoff = $before ?? $this->olderThanCutoff($after);

        if (! $cutoff instanceof CarbonInterface) {
            $this->components->info('Nothing to prune, `retention.prune.after` is not set.');

            return self::SUCCESS;
        }

        return $this->exclusively(function (Deadline $deadline) use ($prune, $cutoff, $chunk): int {
            $this->report('deleted', $prune->handle($cutoff, $chunk, $this->isDryRun(), $deadline));

            return self::SUCCESS;
        }, $deadline);
    }

    private function olderThanCutoff(?Duration $after): ?CarbonInterface
    {
        if (! $after instanceof Duration) {
            return null;
        }

        return $after->before(Carbon::now());
    }

    /**
     * It returns false once the error is reported.
     */
    private function before(): CarbonInterface|false|null
    {
        $before = $this->option('before');

        if (! is_string($before)) {
            return null;
        }

        if ($this->option('older-than') !== null) {
            $this->components->error('Pass either --before or --older-than, not both.');

            return false;
        }

        try {
            return Carbon::parse($before);
        } catch (InvalidFormatException) {
            $this->components->error('The --before option must be a date such as `2026-01-01`.');

            return false;
        }
    }
}
