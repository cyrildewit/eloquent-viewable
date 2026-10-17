<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\Console\Concerns\ReportsRetention;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Console\MaintenanceCommand;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Support\Carbon;

final class PruneViewsCommand extends MaintenanceCommand
{
    use ReportsRetention;

    #[\Override]
    protected $signature = 'views:prune
        {--older-than= : Delete views older than this, such as 90d, instead of retention.prune.after}
        {--before= : Delete views viewed before this date, such as the start of a dropped partition}
        {--chunk= : How many views to delete per statement}
        {--max-seconds= : Stop starting new chunks after this many seconds, the next run carries on}
        {--dry-run : Count the views that would be deleted without deleting them}';

    #[\Override]
    protected $description = 'Delete old views';

    /** @throws InvalidOption */
    public function handle(PruneViews $prune, RetentionPolicy $policy, RunLock $lock): int
    {
        $chunk = $this->positiveInteger('chunk', $policy->chunk);
        $cutoff = $this->before() ?? $this->duration('older-than', $policy->pruneAfter)?->before(Carbon::now());
        $deadline = $this->deadline();

        if (! $cutoff instanceof CarbonInterface) {
            $this->components->info('Nothing to prune, `retention.prune.after` is not set.');

            return self::SUCCESS;
        }

        return $this->exclusively($lock, function (Deadline $deadline) use ($prune, $cutoff, $chunk): int {
            $this->reportRetention('deleted', $prune->handle($cutoff, $chunk, $this->isDryRun(), $deadline));

            return self::SUCCESS;
        }, $deadline);
    }

    /** @throws InvalidOption */
    private function before(): ?CarbonInterface
    {
        $before = $this->date('before', '2026-01-01');

        if ($before instanceof CarbonInterface && $this->option('older-than') !== null) {
            throw new InvalidOption('Pass either --before or --older-than, not both.');
        }

        return $before;
    }
}
