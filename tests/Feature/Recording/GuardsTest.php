<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreBursts;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreHeadRequests;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreMissingUserAgent;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreOptedOutViewers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch;
use CyrildeWit\EloquentViewable\Recording\Guards\ThrottleVisitors;
use CyrildeWit\EloquentViewable\Support\Config as PackageConfig;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\NotAGuard;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Guards\RefuseAll;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\OptOutUser;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
    RefuseAll::$calls = 0;
});

function alwaysCrawler(): CrawlerDetector
{
    return new class implements CrawlerDetector
    {
        public function isCrawler(?string $userAgent): bool
        {
            return true;
        }
    };
}

it('ships with the crawler, user agent, IP address, HEAD, prefetch, opt-out, burst and cooldown guards listed', function (): void {
    expect($this->app->make(PackageConfig::class)->guards())->toBe([
        IgnoreCrawlers::class,
        IgnoreMissingUserAgent::class,
        IgnoreIpAddresses::class,
        IgnoreHeadRequests::class,
        IgnorePrefetch::class,
        IgnoreOptedOutViewers::class,
        IgnoreBursts::class,
        EnforceCooldown::class,
    ]);
});

it('runs a guard added to the config', function (): void {
    Config::set('eloquent-viewable.recording.guards', [RefuseAll::class]);

    expect(views($this->post)->record())->toBeFalse()
        ->and(RefuseAll::$calls)->toBe(1)
        ->and(View::count())->toBe(0);
});

it('stops at the first guard that refuses', function (): void {
    Config::set('eloquent-viewable.recording.guards', [RefuseAll::class, RefuseAll::class]);

    views($this->post)->record();

    expect(RefuseAll::$calls)->toBe(1);
});

it('dispatches ViewSkipped with the guard that refused', function (): void {
    Config::set('eloquent-viewable.recording.guards', [RefuseAll::class]);

    Event::fake([ViewSkipped::class]);

    views($this->post)->collection('sidebar')->record();

    Event::assertDispatched(ViewSkipped::class, fn (ViewSkipped $event): bool => $event->guard instanceof RefuseAll
        && $event->attempt->viewable->is($this->post)
        && $event->attempt->collection === 'sidebar');
});

it('dispatches nothing when every guard allows', function (): void {
    Event::fake([ViewSkipped::class]);

    views($this->post)->record();

    Event::assertNotDispatched(ViewSkipped::class);
});

it('ignores bot views until IgnoreCrawlers is removed from the list', function (): void {
    $this->app->instance(CrawlerDetector::class, alwaysCrawler());

    expect(views($this->post)->record())->toBeFalse();

    Config::set('eloquent-viewable.recording.guards', [EnforceCooldown::class]);

    expect(views($this->post)->record())->toBeTrue()
        ->and(View::count())->toBe(1);
});

it('ignores listed ip addresses until IgnoreIpAddresses is removed from the list', function (): void {
    Config::set('eloquent-viewable.recording.ignored_ip_addresses', ['127.0.0.1']);

    expect(views($this->post)->record())->toBeFalse();

    Config::set('eloquent-viewable.recording.guards', [EnforceCooldown::class]);

    expect(views($this->post)->record())->toBeTrue()
        ->and(View::count())->toBe(1);
});

it('honours Do Not Track once IgnoreDoNotTrack is listed', function (): void {
    $this->app['request']->headers->set('DNT', '1');

    expect(views($this->post)->record())->toBeTrue();

    Config::set('eloquent-viewable.recording.guards', [IgnoreDoNotTrack::class]);

    expect(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(1);
});

it('honours Global Privacy Control once IgnoreGlobalPrivacyControl is listed', function (): void {
    $this->app['request']->headers->set('Sec-GPC', '1');

    expect(views($this->post)->record())->toBeTrue();

    Config::set('eloquent-viewable.recording.guards', [IgnoreGlobalPrivacyControl::class]);

    expect(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(1);
});

describe('opting out', function (): void {
    beforeEach(function (): void {
        $this->user = OptOutUser::query()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
        $this->actingAs($this->user);
    });

    it('drops the views of a signed-in user who opted out, with viewers not recorded', function (): void {
        $this->user->hidesReadingHistory = true;

        $result = views($this->post)->attempt();

        expect($result->wasSkippedBy(IgnoreOptedOutViewers::class))->toBeTrue()
            ->and(View::count())->toBe(0);
    });

    it('drops the views of a signed-in user who opted out, with viewers recorded', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        $this->user->hidesReadingHistory = true;

        expect(views($this->post)->record())->toBeFalse()
            ->and(View::count())->toBe(0);

        $this->user->hidesReadingHistory = false;

        expect(views($this->post)->record())->toBeTrue()
            ->and(View::sole()->viewer_id)->toEqual($this->user->getKey());
    });

    it('starts no cooldown for a view it drops', function (): void {
        $this->user->hidesReadingHistory = true;

        expect(views($this->post)->cooldown(60)->record())->toBeFalse();

        $this->user->hidesReadingHistory = false;

        expect(views($this->post)->cooldown(60)->record())->toBeTrue()
            ->and(View::count())->toBe(1);
    });

    it('records the views of a user who opted out once IgnoreOptedOutViewers is removed from the list', function (): void {
        $this->user->hidesReadingHistory = true;
        Config::set('eloquent-viewable.recording.guards', [EnforceCooldown::class]);

        expect(views($this->post)->record())->toBeTrue()
            ->and(View::count())->toBe(1);
    });
});

it('drops a page the browser only prefetches', function (): void {
    $this->app['request']->headers->set('Sec-Purpose', 'prefetch');

    expect(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(0);
});

it('drops a request without a user agent', function (): void {
    $this->app['request']->headers->remove('User-Agent');

    expect(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(0);
});

it('drops a HEAD request', function (): void {
    $this->app['request']->setMethod('HEAD');

    expect(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(0);
});

it('throttles a visitor across viewables once ThrottleVisitors is listed', function (): void {
    Config::set('eloquent-viewable.recording.guards', [ThrottleVisitors::class]);
    Config::set('eloquent-viewable.recording.throttle.max_per_minute', 2);

    $other = Post::factory()->create();

    expect(views($this->post)->record())->toBeTrue()
        ->and(views($other)->record())->toBeTrue()
        ->and(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(2);
});

it('drops a burst of views of different models with the default guards', function (): void {
    $posts = Post::factory()->count(9)->create();

    $recorded = $posts->map(fn (Post $post): bool => views($post)->record());

    expect($recorded->take(8)->every(fn (bool $stored): bool => $stored))->toBeTrue()
        ->and($recorded->last())->toBeFalse()
        ->and(views($this->post)->record())->toBeFalse()
        ->and(View::count())->toBe(8);
});

it('rejects a guard class that does not implement the contract', function (): void {
    Config::set('eloquent-viewable.recording.guards', [NotAGuard::class]);

    expect(fn (): bool => views($this->post)->record())
        ->toThrow(InvalidConfiguration::class, 'Every class in `eloquent-viewable.recording.guards` must implement `'.RecordingGuard::class.'`, `'.NotAGuard::class.'` does not.');
});

it('starts no cooldown for a view a guard listed after the cooldown drops', function (): void {
    Config::set('eloquent-viewable.recording.guards', [EnforceCooldown::class, IgnoreCrawlers::class]);
    $this->app->instance(CrawlerDetector::class, alwaysCrawler());

    expect(views($this->post)->cooldown(60)->record())->toBeFalse();

    $this->app->instance(CrawlerDetector::class, new class implements CrawlerDetector
    {
        public function isCrawler(?string $userAgent): bool
        {
            return false;
        }
    });

    expect(views($this->post)->cooldown(60)->record())->toBeTrue()
        ->and(views($this->post)->cooldown(60)->record())->toBeFalse()
        ->and(View::count())->toBe(1);
});
