<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Samples\ContentDashboard\ContentDashboard;
use CyrildeWit\EloquentViewable\Samples\ContentDashboard\Episode;
use CyrildeWit\EloquentViewable\Samples\ContentDashboard\Guide;
use CyrildeWit\EloquentViewable\Samples\ContentDashboard\ShowDashboard;
use CyrildeWit\EloquentViewable\Support\Period;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 12:00'));

    // The web middleware encrypts the session cookie.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::get('/dashboard/{period}', ShowDashboard::class)->middleware('web');
});

/**
 * Stores `$count` views at a chosen time, the way the content pages would
 * have recorded them, through the factory the package ships.
 */
function viewContent(Model&Viewable $content, string $at, int $count = 1, ?string $placement = null): void
{
    View::factory()
        ->count($count)
        ->for($content, 'viewable')
        ->inCollection($placement)
        ->viewedAt(Carbon::parse($at))
        ->create();
}

it('ranks guides and episodes together', function (): void {
    $guide = Guide::create(['title' => 'Indexing JSON columns']);
    $episode = Episode::create(['title' => 'Postgres at scale']);
    $quiet = Guide::create(['title' => 'Naming things']);

    viewContent($guide, '2026-09-30 10:00', 3);
    viewContent($episode, '2026-09-29 10:00', 5);
    viewContent($quiet, '2026-10-01 10:00');
    viewContent($quiet, '2026-08-01 10:00', 10);

    $this->get('/dashboard/7d')
        ->assertOk()
        ->assertJsonPath('period', '7d')
        ->assertJsonPath('top', [
            ['rank' => 1, 'type' => 'Episode', 'title' => 'Postgres at scale', 'views' => 5],
            ['rank' => 2, 'type' => 'Guide', 'title' => 'Indexing JSON columns', 'views' => 3],
            ['rank' => 3, 'type' => 'Guide', 'title' => 'Naming things', 'views' => 1],
        ]);
});

it('answers a 404 for a period it cannot read', function (): void {
    $this->get('/dashboard/last-week')->assertNotFound();
});

it('reads a range of dates from the url', function (): void {
    $guide = Guide::create(['title' => 'Indexing JSON columns']);

    viewContent($guide, '2026-08-31 23:00');
    viewContent($guide, '2026-09-01 10:00', 2);
    viewContent($guide, '2026-10-01 10:00');

    $this->get('/dashboard/2026-09-01..2026-10-01')
        ->assertOk()
        ->assertJsonPath('top.0.views', 2);
});

it('compares each type with the period before', function (): void {
    $guide = Guide::create(['title' => 'Indexing JSON columns']);
    $episode = Episode::create(['title' => 'Postgres at scale']);

    viewContent($guide, '2026-09-30 10:00', 3);
    viewContent($guide, '2026-09-20 10:00');
    viewContent($episode, '2026-09-30 10:00', 2);

    $trends = app(ContentDashboard::class)->for(Period::parse('7d'))->trends;

    expect($trends['guides'])
        ->current->toBe(3)
        ->previous->toBe(1)
        ->percent->toBe(200.0)
        ->and($trends['episodes'])
        ->current->toBe(2)
        ->previous->toBe(0)
        ->percent->toBeNull();
});

it('has no trend for a period that is open on one side', function (): void {
    $report = app(ContentDashboard::class)->for(Period::parse('2026-09-01..'));

    expect($report->trends)->toBe(['guides' => null, 'episodes' => null]);
});

it('adds up the views per placement across both types', function (): void {
    $guide = Guide::create(['title' => 'Indexing JSON columns']);
    $episode = Episode::create(['title' => 'Postgres at scale']);

    viewContent($guide, '2026-09-30 10:00', 2);
    viewContent($guide, '2026-09-30 10:00', 3, 'newsletter');
    viewContent($episode, '2026-09-30 10:00', 4, 'newsletter');
    viewContent($episode, '2026-09-30 10:00', 1, 'search');

    $report = app(ContentDashboard::class)->for(Period::parse('7d'));

    expect($report->placements)->toBe(['newsletter' => 7, 'direct' => 2, 'search' => 1]);
});

it('counts the latest guides, those without views too', function (): void {
    $viewed = Guide::create(['title' => 'Indexing JSON columns']);
    $unviewed = Guide::create(['title' => 'Naming things']);

    viewContent($viewed, '2026-09-30 10:00', 2);

    $this->get('/dashboard/7d')
        ->assertOk()
        ->assertJsonPath('latest_guides', [
            ['title' => 'Naming things', 'views' => 0],
            ['title' => 'Indexing JSON columns', 'views' => 2],
        ]);
});

it('starts the days at midnight on the editors\' clock', function (): void {
    // 23:30 in UTC is half past one the next morning in Amsterdam, so the
    // past day began at 22:00 UTC the day before.
    $this->travelTo(Carbon::parse('2026-10-01 23:30'));

    $guide = Guide::create(['title' => 'Indexing JSON columns']);
    viewContent($guide, '2026-09-30 21:30');
    viewContent($guide, '2026-09-30 22:30', 2);

    $this->get('/dashboard/1d')
        ->assertOk()
        ->assertJsonPath('top.0.views', 2);
});
