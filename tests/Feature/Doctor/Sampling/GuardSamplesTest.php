<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Doctor\Sampling\GuardSamples;
use CyrildeWit\EloquentViewable\EloquentViewableServiceProvider;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

function samples(): GuardSamples
{
    return app()->make(GuardSamples::class);
}

beforeEach(function (): void {
    config()->set('eloquent-viewable.doctor.sample.store', 'array');

    Carbon::setTestNow('2024-05-08 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('counts the recorded and refused attempts of the last days', function (): void {
    samples()->countRecorded();
    samples()->countRefused(app()->make(IgnoreCrawlers::class));

    Carbon::setTestNow('2024-05-02 12:00:00');

    samples()->countRecorded();
    samples()->countRecorded();

    Carbon::setTestNow('2024-05-01 12:00:00');

    samples()->countRefused(app()->make(IgnoreCrawlers::class));

    Carbon::setTestNow('2024-05-08 12:00:00');

    $sample = samples()->lastDays([IgnoreCrawlers::class, IgnoreHeadRequests::class]);

    expect($sample->days)->toBe(GuardSamples::Days)
        ->and($sample->recorded)->toBe(3)
        ->and($sample->refused)->toBe([IgnoreCrawlers::class => 1, IgnoreHeadRequests::class => 0]);
});

it('leaves the attempt alone when the cache cannot be reached', function (): void {
    config()->set('eloquent-viewable.doctor.sample.store', 'missing');

    samples()->countRecorded();

    config()->set('eloquent-viewable.doctor.sample.store', 'array');

    expect(samples()->lastDays([])->recorded)->toBe(0);
});

it('samples what the recorder does once it is turned on', function (): void {
    config()->set('eloquent-viewable.doctor.sample.enabled', true);

    app()->getProvider(EloquentViewableServiceProvider::class)?->boot();

    $post = Post::factory()->create();

    views($post)->record();

    request()->headers->set('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)');
    app()->forgetInstance(CrawlerDetect::class);

    views($post)->record();

    $sample = samples()->lastDays([IgnoreCrawlers::class]);

    expect($sample->recorded)->toBe(1)
        ->and($sample->refusedBy(IgnoreCrawlers::class))->toBe(1);
});

it('samples nothing while it is off', function (): void {
    views(Post::factory()->create())->record();

    expect(samples()->lastDays([])->recorded)->toBe(0);
});
