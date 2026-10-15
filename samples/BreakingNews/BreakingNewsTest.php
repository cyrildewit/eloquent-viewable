<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use CyrildeWit\EloquentViewable\Samples\BreakingNews\FlushStoryViews;
use CyrildeWit\EloquentViewable\Samples\BreakingNews\Newsroom;
use CyrildeWit\EloquentViewable\Samples\BreakingNews\ShowStory;
use CyrildeWit\EloquentViewable\Samples\BreakingNews\Story;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

const STORY_STREAM = 'eloquent-viewable:views';

beforeEach(function (): void {
    // The web middleware encrypts the session and visitor cookies.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::get('/stories/{story}', ShowStory::class)->middleware('web');

    // The `redis` store, through Predis, on an empty stream.
    config(['database.redis.client' => 'predis']);
    config(['eloquent-viewable.recording.store.driver' => 'redis']);
    $this->app->forgetInstance('redis');
    $this->app->make(StoreManager::class)->forgetDrivers();
    $this->app->make(RedisFactory::class)->connection()->command('del', [STORY_STREAM]);
});

/**
 * Opens the story `$count` times, each time as a different reader.
 */
function readStory(Story $story, int $count = 1): void
{
    for ($i = 0; $i < $count; $i++) {
        session()->flush();

        test()->get("/stories/{$story->id}")->assertOk();
    }
}

function bufferedViews(): int
{
    return (int) app(RedisFactory::class)->connection()->command('xlen', [STORY_STREAM]);
}

it('buffers the view instead of writing a row during the request', function (): void {
    $story = Story::create(['headline' => 'Dam breaks upstream']);

    DB::enableQueryLog();

    readStory($story);

    $writes = array_filter(DB::getQueryLog(), fn (array $query): bool => ! str_starts_with(strtolower($query['query']), 'select'));

    expect($writes)->toBeEmpty()
        ->and(View::count())->toBe(0)
        ->and(bufferedViews())->toBe(1);
});

it('lands the buffered views when the flush runs', function (): void {
    $story = Story::create(['headline' => 'Dam breaks upstream']);

    readStory($story, 3);

    expect(app(FlushStoryViews::class)())->toBe(3)
        ->and($story)->toHaveViewsCount(3)
        ->and(bufferedViews())->toBe(0);
});

it('reports the counts as of the last flush', function (): void {
    CarbonImmutable::setTestNow('2026-03-01 09:00:00');
    $dam = Story::create(['headline' => 'Dam breaks upstream']);
    $vote = Story::create(['headline' => 'Council vote postponed']);

    readStory($dam, 2);
    readStory($vote);
    app(FlushStoryViews::class)();

    CarbonImmutable::setTestNow('2026-03-01 09:00:30');
    readStory($dam);

    $report = app(Newsroom::class)->today();

    expect($report->views)->toBe(['Dam breaks upstream' => 2, 'Council vote postponed' => 1])
        ->and($report->asOf?->toDateTimeString())->toBe('2026-03-01 09:00:00');

    app(FlushStoryViews::class)();

    $report = app(Newsroom::class)->today();

    expect($report->views['Dam breaks upstream'])->toBe(3)
        ->and($report->asOf?->toDateTimeString())->toBe('2026-03-01 09:00:30');
});

it('has no freshness to report before the first flush', function (): void {
    Story::create(['headline' => 'Dam breaks upstream']);

    expect(app(Newsroom::class)->today())
        ->views->toBe(['Dam breaks upstream' => 0])
        ->asOf->toBeNull();
});

it('does not buffer a view for a reader who refreshes within the cooldown', function (): void {
    $story = Story::create(['headline' => 'Dam breaks upstream']);

    $this->get("/stories/{$story->id}")->assertOk();
    $this->withCookie(config('session.cookie'), session()->getId())
        ->get("/stories/{$story->id}")
        ->assertOk();

    expect(bufferedViews())->toBe(1);
});

it('drops the buffered views of a story that is taken down', function (): void {
    $story = Story::create(['headline' => 'Dam breaks upstream']);
    $other = Story::create(['headline' => 'Council vote postponed']);

    readStory($story, 2);
    readStory($other);
    app(FlushStoryViews::class)();
    readStory($story);
    readStory($other);

    $story->delete();

    expect(bufferedViews())->toBe(1)
        ->and(View::count())->toBe(1);

    app(FlushStoryViews::class)();

    expect($other)->toHaveViewsCount(2)
        ->and(View::count())->toBe(2);
});
