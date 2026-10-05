<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost as Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'cached_unique_views' => ['unique' => true, 'period' => '7d', 'collection' => null],
    ]]);

    [$this->post, $this->trashed, $this->unseen] = array_map(
        fn (int $number): Post => Post::query()->create(['title' => "Post {$number}", 'body' => 'Body']),
        [1, 2, 3],
    );

    foreach ([
        [$this->post, '2026-01-10 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-28 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-29 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-30 10:00:00', 'visitor-2'],
        [$this->trashed, '2026-03-30 10:00:00', 'visitor-1'],
    ] as [$post, $viewedAt, $visitor]) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->create();
    }

    $this->trashed->delete();
});

/** @return list<array{int, int}> */
function counted(Post ...$posts): array
{
    return array_map(function (Post $post): array {
        $fresh = Post::withTrashed()->findOrFail($post->getKey());

        return [(int) $fresh->getAttribute('cached_views'), (int) $fresh->getAttribute('cached_unique_views')];
    }, $posts);
}

it('writes the count of every counter column, trashed models included', function (): void {
    $recounted = app(RecountViews::class)->handle(chunk: 2);

    expect($recounted)->toBe([Post::class => 3])
        ->and(counted($this->post, $this->trashed, $this->unseen))->toBe([[4, 2], [1, 1], [0, 0]]);
});

it('counts what the rollups hold once the views are deleted', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);
    app(RecountViews::class)->handle(chunk: 100);

    expect(counted($this->post))->toBe([[4, 2]])
        ->and(Post::query()->orderByDesc('cached_views')->value('id'))->toBe($this->post->getKey());
});

it('is registered', function (): void {
    expect(Artisan::all())->toHaveKey('views:recount');
});

it('recounts from the command line', function (): void {
    $this->artisan('views:recount', ['--chunk' => '1'])
        ->expectsOutputToContain('Recounted 3 SoftDeletablePosts.')
        ->assertSuccessful();

    expect(counted($this->post))->toBe([[4, 2]]);
});

it('says so when no counter is configured', function (): void {
    config()->set('eloquent-viewable.querying.counters', []);

    $this->artisan('views:recount')
        ->expectsOutputToContain('Nothing to recount, `querying.counters` is empty.')
        ->assertSuccessful();
});

it('recounts in chunks of the configured size by default', function (): void {
    config()->set('eloquent-viewable.retention.chunk', 1);

    $this->artisan('views:recount')
        ->expectsOutputToContain('Recounted 3 SoftDeletablePosts.')
        ->assertSuccessful();

    expect(counted($this->post))->toBe([[4, 2]]);
});

it('rejects a chunk size that is not a positive integer', function (string $chunk): void {
    $this->artisan('views:recount', ['--chunk' => $chunk])
        ->expectsOutputToContain('The --chunk option must be a positive integer.')
        ->assertFailed();
})->with(['zero' => '0', 'not a number' => 'many']);

it('recounts last when maintaining', function (): void {
    config()->set('eloquent-viewable.retention.prune.after', '30d');

    $this->artisan('views:maintain')
        ->expectsOutputToContain('Deleted 1 view')
        ->expectsOutputToContain('Recounted 3 SoftDeletablePosts.')
        ->assertSuccessful();

    expect(counted($this->post))->toBe([[3, 2]]);
});

it('recounts when maintaining nothing else', function (): void {
    $this->artisan('views:maintain')
        ->expectsOutputToContain('Recounted 3 SoftDeletablePosts.')
        ->assertSuccessful();
});

it('recounts nothing on a dry run', function (): void {
    $this->artisan('views:maintain', ['--dry-run' => true])
        ->doesntExpectOutputToContain('Recounted')
        ->assertSuccessful();

    expect(counted($this->post))->toBe([[0, 0]]);
});

it('refuses to recount through a source that cannot be queried in SQL', function (): void {
    ViewsFacade::fake();

    app(RecountViews::class)->handle(chunk: 100);
})->throws(UnsupportedBySource::class, 'cannot be queried in SQL, so views:recount cannot write the counter columns from it.');

it('stops recounting at its time limit and carries on in the next run', function (): void {
    travelOnFirst('update', 'posts');

    $this->artisan('views:recount', ['--chunk' => '1', '--max-seconds' => '60'])
        ->expectsOutputToContain('Recounted 1 SoftDeletablePost.')
        ->expectsOutputToContain('Stopped at the time limit. The next run carries on from here.')
        ->assertSuccessful();

    $this->artisan('views:recount')
        ->expectsOutputToContain('Recounted 2 SoftDeletablePosts.')
        ->assertSuccessful();
});

it('recounts only what changed, or every model with --full', function (): void {
    $this->artisan('views:recount')->assertSuccessful();

    $this->artisan('views:recount')
        ->expectsOutputToContain('Recounted 0 SoftDeletablePosts.')
        ->assertSuccessful();

    $this->artisan('views:recount', ['--full' => true])
        ->expectsOutputToContain('Recounted 3 SoftDeletablePosts.')
        ->assertSuccessful();
});

it('skips the recount while another run holds the lock', function (): void {
    $lock = Cache::lock('cyrildewit.eloquent-viewable.cache:maintenance', 10);
    $lock->get();

    try {
        $this->artisan('views:recount')
            ->expectsOutputToContain('Another run is in progress, so this one was skipped.')
            ->assertSuccessful();
    } finally {
        $lock->release();
    }

    expect(counted($this->post))->toBe([[0, 0]]);
});

it('recounts a single model through the counters of its class only', function (): void {
    app(RecountViews::class)->destroyed(new Post);
    app(RecountViews::class)->recount(new Post, [$this->post->getKey()]);

    config()->set('eloquent-viewable.querying.counters', []);
    app(RecountViews::class)->recount(new Post, [$this->trashed->getKey()]);

    expect(counted($this->post, $this->trashed))->toBe([[4, 2], [0, 0]]);
});

it('skips recounting a model whose views were destroyed under a source that cannot be queried in SQL', function (): void {
    ViewsFacade::fake();

    app(RecountViews::class)->destroyed($this->post);

    expect(counted($this->post))->toBe([[0, 0]]);
});
