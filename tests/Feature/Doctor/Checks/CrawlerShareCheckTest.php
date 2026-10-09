<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Checks\CrawlerShareCheck;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use CyrildeWit\EloquentViewable\Doctor\Data\Status;
use CyrildeWit\EloquentViewable\Doctor\Sampling\GuardSamples;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;

/** @return list<array{Status, string}> */
function crawlerFindings(): array
{
    $findings = iterator_to_array(app()->make(CrawlerShareCheck::class)->run(), preserve_keys: false);

    return array_map(fn (Finding $finding): array => [$finding->status, $finding->summary], $findings);
}

function sampleAttempts(int $recorded, int $crawlers = 0, int $prefetches = 0): void
{
    $samples = app()->make(GuardSamples::class);

    for ($i = 0; $i < $recorded; $i++) {
        $samples->countRecorded();
    }

    for ($i = 0; $i < $crawlers; $i++) {
        $samples->countRefused(app()->make(IgnoreCrawlers::class));
    }

    for ($i = 0; $i < $prefetches; $i++) {
        $samples->countRefused(app()->make(IgnorePrefetch::class));
    }
}

beforeEach(function (): void {
    config()->set('eloquent-viewable.doctor.sample.enabled', true);
    config()->set('eloquent-viewable.doctor.sample.store', 'array');
});

it('advises to turn sampling on', function (): void {
    config()->set('eloquent-viewable.doctor.sample.enabled', false);

    expect(crawlerFindings())->toBe([
        [Status::Advice, 'Sampling is off, so the share of attempts each guard refuses is unknown.'],
    ]);
});

it('advises to list IgnoreCrawlers', function (): void {
    config()->set('eloquent-viewable.recording.guards', [IgnorePrefetch::class]);

    sampleAttempts(recorded: 3, prefetches: 1);

    expect(crawlerFindings())->toBe([
        [Status::Advice, '`IgnoreCrawlers` is not listed in `recording.guards`, so the visits of crawlers count as views.'],
        [Status::Pass, 'Of 4 attempts in the last 7 days, `IgnorePrefetch` refused 25%.'],
    ]);
});

it('says when nothing was sampled', function (): void {
    expect(crawlerFindings())->toBe([
        [Status::Advice, 'No attempts were sampled in the last 7 days.'],
    ]);
});

it('passes when every attempt was recorded', function (): void {
    sampleAttempts(recorded: 1_500);

    expect(crawlerFindings())->toBe([
        [Status::Pass, 'Of 1,500 attempts in the last 7 days, every one was recorded.'],
    ]);
});

it('breaks the refusals down by guard', function (): void {
    sampleAttempts(recorded: 6, crawlers: 3, prefetches: 1);

    expect(crawlerFindings())->toBe([
        [Status::Pass, 'Of 10 attempts in the last 7 days, `IgnoreCrawlers` refused 30%, `IgnorePrefetch` refused 10%.'],
    ]);
});

it('warns when crawlers make up more than the configured share', function (): void {
    config()->set('eloquent-viewable.doctor.sample.crawler_share', 0.25);

    sampleAttempts(recorded: 2, crawlers: 1);

    expect(crawlerFindings()[1])->toBe([Status::Warning, '`IgnoreCrawlers` refused 33.3% of the attempts, more than the 25% of `doctor.sample.crawler_share`.']);
});
