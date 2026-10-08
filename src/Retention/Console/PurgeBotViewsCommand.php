<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Retention\Console;

use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Retention\Actions\PurgeBotViews;
use CyrildeWit\EloquentViewable\Retention\Data\PurgeRun;
use CyrildeWit\EloquentViewable\Retention\RetentionPolicy;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Console\ConfirmsInProduction;
use CyrildeWit\EloquentViewable\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class PurgeBotViewsCommand extends RetentionCommand
{
    use ConfirmsInProduction;

    #[\Override]
    protected $signature = 'views:purge-bots
        {--since=1d : How far back to look, a duration such as 7d or a date such as 2026-01-01}
        {--max= : More than this many different models within --seconds is a burst, instead of recording.bursts.max}
        {--seconds= : The window of a burst, instead of recording.bursts.seconds}
        {--whole-visitor : Delete every view of a visitor with --min-bursts separate bursts, not only the views in a burst}
        {--min-bursts=3 : How many separate bursts --whole-visitor needs}
        {--include-viewers : Also delete views of a signed-in viewer}
        {--chunk= : How many views to read and delete per statement}
        {--dry-run : Count the views that would be deleted without deleting them}
        {--force : Delete without asking in production}';

    #[\Override]
    protected $description = 'Delete the views of bots that opened many models within seconds';

    /** @throws InvalidConfiguration */
    public function handle(PurgeBotViews $purge, RetentionPolicy $policy, Config $config): int
    {
        $since = $this->since();
        $max = $this->positiveInteger('max', $config->burstMax());
        $seconds = $this->positiveInteger('seconds', $config->burstSeconds());
        $minBursts = $this->positiveInteger('min-bursts', 3);
        $chunk = $this->chunk($policy);

        if (! $since instanceof CarbonInterface) {
            return self::FAILURE;
        }

        if ($max === false) {
            return self::FAILURE;
        }

        if ($seconds === false) {
            return self::FAILURE;
        }

        if ($minBursts === false) {
            return self::FAILURE;
        }

        if ($chunk === null) {
            return self::FAILURE;
        }

        $wholeVisitor = (bool) $this->option('whole-visitor');

        if ($this->sharesVisitorIds($wholeVisitor, $config)) {
            $this->components->error('The --whole-visitor option cannot be used with the `fingerprint` identity, because people on one network with the same browser share a visitor id.');

            return self::FAILURE;
        }

        if (! $this->confirmed()) {
            return self::FAILURE;
        }

        return $this->exclusively(function () use ($purge, $config, $since, $max, $seconds, $minBursts, $chunk, $wholeVisitor): int {
            $run = $purge->handle(
                since: $since,
                max: $max,
                seconds: $seconds,
                chunk: $chunk,
                includeViewers: (bool) $this->option('include-viewers'),
                minBursts: $wholeVisitor ? $minBursts : null,
                dryRun: $this->isDryRun(),
            );

            $this->reportPurge($run, $config);

            return self::SUCCESS;
        });
    }

    /**
     * It returns null once the error is reported.
     */
    private function since(): ?CarbonInterface
    {
        $option = $this->option('since');
        $option = is_string($option) ? $option : '1d';

        $duration = Duration::tryParse($option);

        if ($duration instanceof Duration) {
            return $duration->before(Carbon::now());
        }

        try {
            return Carbon::parse($option);
        } catch (InvalidFormatException) {
            $this->components->error('The --since option must be a duration such as `7d` or a date such as `2026-01-01`.');

            return null;
        }
    }

    /** @throws InvalidConfiguration */
    private function reportPurge(PurgeRun $run, Config $config): void
    {
        $views = Str::plural('view', $run->views);
        $visitors = Str::plural('visitor', $run->visitors);
        $summary = "deleted {$run->views} {$views} of {$run->visitors} {$visitors} viewed since {$run->from->toDateTimeString()}.";

        $this->components->info($run->dryRun ? "Would have {$summary}" : ucfirst($summary));

        if ($run->clamped) {
            $this->components->warn("Started at {$run->from->toDateTimeString()}, because the rollups cannot be folded again before it.");
        }

        if ($this->leftCountersBehind($run, $config)) {
            $this->components->warn('Run `views:recount` to bring the counter columns up to date.');
        }
    }

    /**
     * Under the `fingerprint` identity people on one network with the same
     * browser share a visitor id, so deleting every view of one would delete
     * theirs too.
     *
     * @throws InvalidConfiguration
     */
    private function sharesVisitorIds(bool $wholeVisitor, Config $config): bool
    {
        if (! $wholeVisitor) {
            return false;
        }

        return $config->visitorIdentity() === 'fingerprint';
    }

    private function confirmed(): bool
    {
        if ($this->isDryRun()) {
            return true;
        }

        return $this->confirmInProduction();
    }

    /** @throws InvalidConfiguration */
    private function leftCountersBehind(PurgeRun $run, Config $config): bool
    {
        if ($run->dryRun) {
            return false;
        }

        if ($run->views === 0) {
            return false;
        }

        return $config->counters() !== [];
    }
}
