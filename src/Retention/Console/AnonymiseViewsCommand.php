<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;

final class AnonymiseViewsCommand extends RetentionCommand
{
    #[\Override]
    protected $signature = 'views:anonymise
        {--older-than= : Anonymise views older than this, such as 30d, instead of retention.anonymise.after}
        {--chunk= : How many views to anonymise per statement}
        {--dry-run : Count the views that would be anonymised without changing them}';

    #[\Override]
    protected $description = 'Take what ties a view to a person out of old views';

    public function handle(AnonymiseViews $anonymise, RetentionPolicy $policy): int
    {
        $chunk = $this->chunk($policy);
        $after = $this->olderThan($policy->anonymiseAfter);

        if ($chunk === null) {
            return self::FAILURE;
        }

        if ($after === false) {
            return self::FAILURE;
        }

        if (! $after instanceof Duration) {
            $this->components->info('Nothing to anonymise, `retention.anonymise.after` is not set.');

            return self::SUCCESS;
        }

        return $this->exclusively(function () use ($anonymise, $policy, $after, $chunk): int {
            $this->report('anonymised', $anonymise->handle($after->before(Carbon::now()), $policy->anonymiseColumns, $chunk, $this->isDryRun()));

            return self::SUCCESS;
        });
    }
}
