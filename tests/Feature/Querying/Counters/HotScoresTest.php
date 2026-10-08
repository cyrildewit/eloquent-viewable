<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The guide was written a day before the release notes and got ten times the
 * views, so with a tenfold every day the two score the same. The draft has no
 * timestamp and scores on its views alone.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'hot_score' => ['hot' => ['every' => '1d']],
    ]]);

    $this->guide = Post::factory()->create(['created_at' => '2026-10-06 12:00:00']);
    $this->release = Post::factory()->create(['created_at' => '2026-10-07 12:00:00']);
    $this->fresh = Post::factory()->create(['created_at' => '2026-10-07 12:00:00']);
    $this->draft = Post::factory()->create();

    DB::table('posts')->where('id', $this->draft->getKey())->update(['created_at' => null]);

    View::factory()->count(100)->for($this->guide, 'viewable')->create();
    View::factory()->count(10)->for($this->release, 'viewable')->create();
    View::factory()->count(20)->for($this->fresh, 'viewable')->create();
    View::factory()->count(1_000)->for($this->draft, 'viewable')->create();
});

function hotScore(Post $post): float
{
    return (float) DB::table('posts')->where('id', $post->getKey())->value('hot_score'); // @phpstan-ignore cast.double (a double column)
}

it('writes the logarithm of the count plus a term that grows with time', function (): void {
    app(RecountViews::class)->handle(chunk: 2);

    $day = Carbon::parse('2026-10-07 12:00:00')->getTimestamp() / 86_400;

    expect(hotScore($this->release))->toEqualWithDelta(1 + $day, 0.0001)
        ->and(hotScore($this->guide))->toEqualWithDelta(hotScore($this->release), 0.0001)
        ->and(hotScore($this->draft))->toEqualWithDelta(3.0, 0.0001)
        ->and(DB::table('posts')->where('id', $this->guide->getKey())->value('cached_views'))->toBe(100);
});

it('orders by the hot score, highest first', function (): void {
    app(RecountViews::class)->handle(chunk: 100);

    expect(Post::query()->orderByHot()->pluck('id')->first())->toBe($this->fresh->getKey())
        ->and(Post::query()->orderByHot('hot_score')->pluck('id')->last())->toBe($this->draft->getKey());
});

it('writes only the hot score when a model has no other counter column', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['hot_score' => ['hot' => true]]]);

    app(RecountViews::class)->recount(new Post, [$this->release->getKey()]);
    app(RecountViews::class)->recount(new Post, []);

    expect(hotScore($this->release))->toBeGreaterThan(1)
        ->and(DB::table('posts')->where('id', $this->release->getKey())->value('cached_views'))->toBe(0);
});

it('skips keys whose model is gone', function (): void {
    app(RecountViews::class)->recount(new Post, [999_999]);

    expect(hotScore($this->release))->toBe(0.0);
});

it('recounts every model once the hot option changes', function (): void {
    app(RecountChangedViews::class)->handle(chunk: 100);

    $before = hotScore($this->guide);

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'hot_score' => ['hot' => ['every' => '12h']],
    ]]);

    app(RecountChangedViews::class)->handle(chunk: 100);

    expect(hotScore($this->guide))->toBeGreaterThan($before);
});

it('needs a counter column with the hot option to order by', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);

    Post::query()->orderByHot();
})->throws(InvalidConfiguration::class, 'has no counter column with the `hot` option');
