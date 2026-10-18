<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use CyrildeWit\EloquentViewable\Exceptions\InvalidOption;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Console\Concerns\ReportsRetention;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Console\ReadsOptions;
use CyrildeWit\EloquentViewable\Support\Console\RunsScheduledWork;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Duration;
use CyrildeWit\EloquentViewable\Support\RunLock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class AnonymiseViewsCommand extends Command
{
    use ReadsOptions;
    use ReportsRetention;
    use RunsScheduledWork;

    #[\Override]
    protected $signature = 'views:anonymise
        {--older-than= : Anonymise views older than this, such as 30d, instead of retention.anonymise.after}
        {--chunk= : How many views to anonymise per statement}
        {--max-seconds= : Stop starting new chunks after this many seconds, the next run carries on}
        {--dry-run : Count the views that would be anonymised without changing them}';

    #[\Override]
    protected $description = 'Take what ties a view to a person out of old views';

    /** @throws InvalidOption */
    public function handle(AnonymiseViews $anonymise, RetentionPolicy $policy, RunLock $lock): int
    {
        $chunk = $this->positiveInteger('chunk', $policy->chunk);
        $after = $this->duration('older-than', $policy->anonymiseAfter);
        $deadline = $this->deadline();

        if (! $after instanceof Duration) {
            $this->components->info('Nothing to anonymise, `retention.anonymise.after` is not set.');

            return self::SUCCESS;
        }

        return $this->exclusively($lock, function (Deadline $deadline) use ($anonymise, $policy, $after, $chunk): int {
            $this->reportRetention('anonymised', $anonymise->handle($after->before(Carbon::now()), $policy->anonymiseColumns, $chunk, $this->isDryRun(), $deadline));

            return self::SUCCESS;
        }, $deadline);
    }
}
