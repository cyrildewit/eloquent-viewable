<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreGlobalPrivacyControl;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics\DocPage;
use CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics\RecordPageView;
use CyrildeWit\EloquentViewable\Samples\PrivacyFirstAnalytics\SkippedViews;
use CyrildeWit\EloquentViewable\Testing\ViewsFake;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 10:00'));

    config([
        'eloquent-viewable.visitor.identity' => 'fingerprint',
        'eloquent-viewable.cooldown.store' => 'cache',
        'eloquent-viewable.recording.guards' => [
            IgnoreCrawlers::class,
            IgnoreDoNotTrack::class,
            IgnoreGlobalPrivacyControl::class,
            IgnoreIpAddresses::class,
            EnforceCooldown::class,
        ],
        'eloquent-viewable.recording.ignored_ip_addresses' => ['10.20.0.0/16', '2001:db8:20::/48'],
    ]);

    Route::post('/api/docs/{page}/views', RecordPageView::class)->middleware('api');

    Event::listen(ViewSkipped::class, SkippedViews::class);

    // Nothing in this sample counts with a scope, so every view can go to
    // the fake and be asserted on directly.
    $this->views = Views::fake();
});

/**
 * Sends the beacon of the page, the way the browser would.
 *
 * @param  array<string, string>  $headers
 */
function sendBeacon(DocPage $page, string $referrer = '', array $headers = [], string $ip = '203.0.113.7'): mixed
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh) Firefox/131.0', ...$headers])
        ->postJson("/api/docs/{$page->id}/views", ['referrer' => $referrer]);
}

function fakeViews(): ViewsFake
{
    return test()->views;
}

it('records a view without setting a cookie', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page)->assertOk()->assertExactJson(['recorded' => true, 'skipped_by' => null]);

    fakeViews()->assertRecorded($page, 1);

    expect(app(CookieJar::class)->getQueuedCookies())->toBeEmpty();
});

it('counts a reader once per page per day', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page)->assertJson(['recorded' => true]);

    $this->travelTo(Carbon::parse('2026-10-01 18:00'));
    sendBeacon($page)->assertJson(['recorded' => false, 'skipped_by' => 'EnforceCooldown']);

    $this->travelTo(Carbon::parse('2026-10-02 09:00'));
    sendBeacon($page)->assertJson(['recorded' => true]);

    $visitors = fakeViews()->recorded($page)->map(fn (ViewRecord $record): string => $record->visitor);

    // The same browser on the same network, under the salt of another day.
    expect($visitors)->toHaveCount(2)
        ->and($visitors->unique())->toHaveCount(2);
});

it('takes readers on one network with the same browser for one reader', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page, ip: '203.0.113.7')->assertJson(['recorded' => true]);
    sendBeacon($page, ip: '203.0.113.99')->assertJson(['skipped_by' => 'EnforceCooldown']);
    sendBeacon($page, ip: '198.51.100.7')->assertJson(['recorded' => true]);

    fakeViews()->assertRecorded($page, 2);
});

it('honours global privacy control and do not track', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page, headers: ['Sec-GPC' => '1'])
        ->assertJson(['recorded' => false, 'skipped_by' => 'IgnoreGlobalPrivacyControl']);
    sendBeacon($page, headers: ['DNT' => '1'])
        ->assertJson(['recorded' => false, 'skipped_by' => 'IgnoreDoNotTrack']);

    fakeViews()->assertNothingRecorded();
});

it('leaves out the staff network', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page, ip: '10.20.3.4')->assertJson(['skipped_by' => 'IgnoreIpAddresses']);
    sendBeacon($page, ip: '2001:db8:20::1')->assertJson(['skipped_by' => 'IgnoreIpAddresses']);
    sendBeacon($page, ip: '10.21.0.1')->assertJson(['recorded' => true]);

    fakeViews()->assertRecorded($page, 1);
});

it('stores where the reader came from, not the referrer', function (string $referrer, string $source): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page, $referrer);

    fakeViews()->assertRecorded($page, fn (ViewRecord $record): bool => $record->context === ['source' => $source]);
})->with([
    'no referrer' => ['', 'direct'],
    'another docs page' => ['http://localhost/docs/upgrading', 'internal'],
    'a search' => ['https://www.google.com/search?q=private+question', 'search'],
    'a lookalike of a search engine' => ['https://notgoogle.com/', 'external'],
    'another site' => ['https://news.ycombinator.com/item?id=1', 'external'],
]);

it('tallies the skipped views of the day per guard', function (): void {
    $page = DocPage::create(['title' => 'Installation']);

    sendBeacon($page);
    sendBeacon($page);
    sendBeacon($page, headers: ['Sec-GPC' => '1']);
    sendBeacon($page, headers: ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);

    expect(app(SkippedViews::class)->on(Carbon::today()))->toBe([
        'IgnoreCrawlers' => 1,
        'IgnoreGlobalPrivacyControl' => 1,
        'EnforceCooldown' => 1,
    ])->and(app(SkippedViews::class)->on(Carbon::yesterday()))->toBeEmpty();
});
