<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Checks;

use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\GuardSample;
use CyrildeWit\EloquentViewable\Doctor\Sampling\GuardSamples;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;

class CrawlerShareCheck implements Check
{
    public function __construct(
        protected Config $config,
        protected GuardSamples $samples,
    ) {}

    public function name(): string
    {
        return 'Refused attempts';
    }

    /**
     * @return Generator<int, Finding>
     *
     * @throws InvalidConfiguration
     */
    public function run(): Generator
    {
        $guards = $this->config->guards();

        if (! in_array(IgnoreCrawlers::class, $guards, true)) {
            yield Finding::advice(
                '`IgnoreCrawlers` is not listed in `recording.guards`, so the visits of crawlers count as views.',
                'List it, unless crawlers should count.',
            );
        }

        if (! $this->config->sampleEnabled()) {
            yield Finding::advice(
                'Sampling is off, so the share of attempts each guard refuses is unknown.',
                'Set `doctor.sample.enabled` to `true` to count them in the cache for a week.',
            );

            return;
        }

        $sample = $this->samples->lastDays($guards);

        if ($sample->attempts() === 0) {
            yield Finding::advice("No attempts were sampled in the last {$sample->days} days.");

            return;
        }

        yield Finding::pass($this->breakdown($sample, $guards));

        if (! in_array(IgnoreCrawlers::class, $guards, true)) {
            return;
        }

        $share = $sample->share(IgnoreCrawlers::class);
        $threshold = $this->config->sampleCrawlerShare();

        if ($share <= $threshold) {
            return;
        }

        $percentage = $this->percentage($share);
        $thresholdPercentage = $this->percentage($threshold);

        yield Finding::warning(
            "`IgnoreCrawlers` refused {$percentage} of the attempts, more than the {$thresholdPercentage} of `doctor.sample.crawler_share`.",
            'A client of your own, such as the webview of an app, may be taken for a crawler, or a scraper may be crawling the site. Bind a `CrawlerDetector` of your own to change the verdict.',
        );
    }

    /** @param  list<string>  $guards */
    protected function breakdown(GuardSample $sample, array $guards): string
    {
        $attempts = number_format($sample->attempts());
        $refusals = [];

        foreach ($guards as $guard) {
            if ($sample->refusedBy($guard) === 0) {
                continue;
            }

            $name = class_basename($guard);

            $refusals[] = "`{$name}` refused {$this->percentage($sample->share($guard))}";
        }

        if ($refusals === []) {
            return "Of {$attempts} attempts in the last {$sample->days} days, every one was recorded.";
        }

        $refused = implode(', ', $refusals);

        return "Of {$attempts} attempts in the last {$sample->days} days, {$refused}.";
    }

    protected function percentage(float $share): string
    {
        $percentage = round($share * 100, 1);

        return "{$percentage}%";
    }
}
