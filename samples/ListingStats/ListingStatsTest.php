<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Samples\ListingStats\Listing;
use CyrildeWit\EloquentViewable\Samples\ListingStats\ListingStats;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 15:00'));

    config()->set('eloquent-viewable.dimensions.definitions', ['source' => Source::class]);
});

/**
 * Stores a view the way `record()` would, but at a chosen time and for a
 * chosen visitor, so a test can lay out a month of traffic in a few lines.
 */
function viewListing(Listing $listing, string $at, string $visitor = 'visitor', ?string $source = null): void
{
    $listing->views()->create(['visitor' => $visitor, 'viewed_at' => Carbon::parse($at), 'source' => $source]);
}

/**
 * @return array<string, int> the count of each bucket, keyed by its date
 */
function countsByDay(iterable $series): array
{
    $counts = [];

    foreach ($series as $bucket) {
        /** @var Bucket $bucket */
        $counts[$bucket->start->toDateString()] = $bucket->count;
    }

    return $counts;
}

it('counts the views per day over the past 30 days, today included', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);

    viewListing($listing, '2026-09-02 09:00');
    viewListing($listing, '2026-09-20 12:00');
    viewListing($listing, '2026-09-20 18:00');
    viewListing($listing, '2026-10-01 14:00');

    $report = app(ListingStats::class)->for($listing);
    $counts = countsByDay($report->views);

    expect($counts)->toHaveCount(30)
        ->and(array_key_first($counts))->toBe('2026-09-02')
        ->and(array_key_last($counts))->toBe('2026-10-01')
        ->and($counts['2026-09-20'])->toBe(2)
        ->and($counts['2026-09-21'])->toBe(0)
        ->and($report->totalViews())->toBe(4);
});

it('counts a returning visitor once for the window but once per day in the chart', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);

    viewListing($listing, '2026-09-28 10:00', 'alice');
    viewListing($listing, '2026-09-28 11:00', 'alice');
    viewListing($listing, '2026-09-30 10:00', 'alice');
    viewListing($listing, '2026-09-30 10:00', 'bob');

    $report = app(ListingStats::class)->for($listing);
    $perDay = countsByDay($report->visitorsPerDay);

    expect($perDay['2026-09-28'])->toBe(1)
        ->and($perDay['2026-09-30'])->toBe(2)
        ->and($report->visitorsPerDay->total())->toBe(3)
        ->and($report->visitors)->toBe(2);
});

it('compares the views with the 30 days before', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);

    viewListing($listing, '2026-08-03 10:00');
    viewListing($listing, '2026-09-01 23:59');
    viewListing($listing, '2026-09-02 00:00');
    viewListing($listing, '2026-09-15 10:00');
    viewListing($listing, '2026-10-01 10:00');

    $report = app(ListingStats::class)->for($listing);

    expect($report->trend)
        ->previous->toBe(2)
        ->current->toBe(3)
        ->delta->toBe(1)
        ->percent->toBe(50.0);
});

it('has no percentage to show when the 30 days before had no views', function (): void {
    $listing = Listing::create(['title' => 'New listing']);

    viewListing($listing, '2026-10-01 10:00');

    expect(app(ListingStats::class)->for($listing)->trend->percent)->toBeNull();
});

it('lists the five largest sources and adds up the rest', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);

    foreach (['Google', 'Google', 'Google', 'Direct', 'Direct', 'Facebook', 'Bing', 'Hacker News', 'Reddit', 'X'] as $source) {
        viewListing($listing, '2026-09-20 10:00', source: $source);
    }

    viewListing($listing, '2026-09-21 10:00');

    $sources = app(ListingStats::class)->for($listing)->sources;

    expect($sources->all())->toBe(['Google' => 3, 'Direct' => 2, 'Bing' => 1, 'Facebook' => 1, 'Hacker News' => 1])
        ->and($sources->other())->toBe(2)
        ->and($sources->none())->toBe(1)
        ->and($sources->share('Google'))->toBe(0.273);
});

it('keeps serving the cached counts for ten minutes', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);
    viewListing($listing, '2026-10-01 14:00', 'alice');
    app(ListingStats::class)->for($listing);

    viewListing($listing, '2026-10-01 15:00', 'bob');

    expect(app(ListingStats::class)->for($listing))
        ->totalViews()->toBe(1)
        ->visitors->toBe(1);

    $this->travel(11)->minutes();

    expect(app(ListingStats::class)->for($listing))
        ->totalViews()->toBe(2)
        ->visitors->toBe(2);
});

it('moves the window on at midnight without waiting for the cache', function (): void {
    $listing = Listing::create(['title' => 'Oak table']);

    $this->travelTo(Carbon::parse('2026-10-01 23:55'));
    viewListing($listing, '2026-09-02 10:00');
    app(ListingStats::class)->for($listing);

    $this->travelTo(Carbon::parse('2026-10-02 00:05'));
    viewListing($listing, '2026-10-02 00:01');

    $counts = countsByDay(app(ListingStats::class)->for($listing)->views);

    expect(array_key_first($counts))->toBe('2026-09-03')
        ->and($counts['2026-10-02'])->toBe(1)
        ->and(app(ListingStats::class)->for($listing)->trend->previous)->toBe(1);
});
