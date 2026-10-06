<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Erasure\Actions\ForgetViewHistory;
use CyrildeWit\EloquentViewable\Erasure\Subject;
use CyrildeWit\EloquentViewable\Erasure\TouchedViewables;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost as Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'cached_unique_views' => ['unique' => true],
    ]]);

    $this->user = User::factory()->create();
    $this->post = Post::query()->create(['title' => 'Post', 'body' => 'Body']);
    $this->other = Post::query()->create(['title' => 'Other', 'body' => 'Body']);

    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->viewedAt(Carbon::parse('2026-03-29 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->by($this->user)->fromVisitor('cookie-1')->viewedAt(Carbon::parse('2026-03-30 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('cookie-2')->viewedAt(Carbon::parse('2026-03-30 10:00:00'))->create();
    View::factory()->for($this->other, 'viewable')->fromVisitor('cookie-2')->viewedAt(Carbon::parse('2026-03-30 10:00:00'))->create();

    app(RecountChangedViews::class)->handle(100);
});

/** @return list<array{int, int}> */
function erasedColumnsOf(Post ...$posts): array
{
    return array_map(function (Post $post): array {
        $fresh = Post::withTrashed()->findOrFail($post->getKey());

        return [(int) $fresh->getAttribute('cached_views'), (int) $fresh->getAttribute('cached_unique_views')];
    }, $posts);
}

it('recounts the counter columns of the models it touched right away', function (): void {
    expect(erasedColumnsOf($this->post, $this->other))->toBe([[3, 2], [1, 1]]);

    $this->user->forgetViewHistory();

    expect(erasedColumnsOf($this->post, $this->other))->toBe([[1, 1], [1, 1]])
        ->and(app(RecountChangedViews::class)->handle(100)->models)->toBe([Post::class => 0]);
});

it('recounts the unique columns after anonymising', function (): void {
    $this->user->anonymiseViewHistory();

    expect(erasedColumnsOf($this->post))->toBe([[3, 3]]);
});

it('recounts a type named by its morph alias', function (): void {
    Relation::morphMap(['post' => Post::class]);

    try {
        $post = Post::query()->create(['title' => 'Aliased', 'body' => 'Body']);

        View::factory()->for($post, 'viewable')->fromVisitor('cookie-3')->count(2)->create();
        View::factory()->for($post, 'viewable')->fromVisitor('cookie-4')->create();
        app(RecountChangedViews::class)->handle(100, full: true);

        expect(erasedColumnsOf($post))->toBe([[3, 2]]);

        app(ForgetViewHistory::class)->handle(Subject::visitor('cookie-3'));

        expect(erasedColumnsOf($post))->toBe([[1, 1]]);
    } finally {
        Relation::morphMap([], false);
    }
});

it('recounts every model on the next run once it touched too many', function (): void {
    foreach (range(0, TouchedViewables::Limit) as $number) {
        $post = Post::query()->create(['title' => "Post {$number}", 'body' => 'Body']);

        View::factory()->for($post, 'viewable')->by($this->user)->create();
    }

    $this->user->forgetViewHistory();

    expect(erasedColumnsOf($this->post))->toBe([[3, 2]])
        ->and(app(RecountChangedViews::class)->handle(100)->models)->toBe([Post::class => TouchedViewables::Limit + 3])
        ->and(erasedColumnsOf($this->post))->toBe([[1, 1]]);
});
