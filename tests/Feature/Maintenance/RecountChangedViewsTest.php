<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Maintenance\Actions\RecountChangedViews;
use CyrildeWit\EloquentViewable\Maintenance\Data\RecountRun;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Retention\Events\BotViewsPurged;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\SoftDeletablePost as Post;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'cached_unique_views' => ['unique' => true, 'period' => '7d'],
    ]]);

    [$this->post, $this->other, $this->unseen] = array_map(
        fn (int $number): Post => Post::query()->create(['title' => "Post {$number}", 'body' => 'Body']),
        [1, 2, 3],
    );

    foreach ([
        [$this->post, '2026-01-10 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-28 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-29 10:00:00', 'visitor-1'],
        [$this->post, '2026-03-30 10:00:00', 'visitor-2'],
        [$this->other, '2026-03-30 10:00:00', 'visitor-1'],
    ] as [$post, $viewedAt, $visitor]) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->create();
    }
});

function recountChanged(int $chunk = 100, ?Deadline $deadline = null, bool $full = false): RecountRun
{
    return app(RecountChangedViews::class)->handle($chunk, $deadline, $full);
}

/** @return list<array{int, int}> */
function columnsOf(Post ...$posts): array
{
    return array_map(function (Post $post): array {
        $fresh = Post::withTrashed()->findOrFail($post->getKey());

        return [(int) $fresh->getAttribute('cached_views'), (int) $fresh->getAttribute('cached_unique_views')];
    }, $posts);
}

it('recounts every model on the first run and only those with new views after it', function (): void {
    expect(recountChanged()->models)->toBe([Post::class => 3]);

    View::factory()->for($this->other, 'viewable')->viewedAt(Carbon::parse('2026-03-31 09:00:00'))->fromVisitor('visitor-3')->create();

    $run = recountChanged();

    expect($run->models)->toBe([Post::class => 1])
        ->and($run->stopped)->toBeFalse()
        ->and(columnsOf($this->post, $this->other, $this->unseen))->toBe([[4, 2], [2, 2], [0, 0]]);
});

it('recounts nothing when nothing changed', function (): void {
    recountChanged();

    expect(recountChanged()->models)->toBe([Post::class => 0]);
});

it('recounts the models whose views left the period of a column', function (): void {
    recountChanged();

    $this->travelTo(Carbon::parse('2026-04-06 12:00:00'));

    expect(recountChanged()->models)->toBe([Post::class => 1])
        ->and(columnsOf($this->post, $this->other))->toBe([[4, 1], [1, 1]]);
});

it('recounts the models with anonymised views when a column counts unique visitors', function (): void {
    recountChanged();

    app(AnonymiseViews::class)->handle(Carbon::parse('2026-03-29'), ['visitor'], 100);

    expect(recountChanged()->models)->toBe([Post::class => 1]);
});

it('leaves anonymised views alone when no column counts unique visitors', function (): void {
    config()->set('eloquent-viewable.querying.counters', [Post::class => ['cached_views']]);
    recountChanged();

    app(AnonymiseViews::class)->handle(Carbon::parse('2026-03-29'), ['visitor'], 100);

    expect(recountChanged()->models)->toBe([Post::class => 0]);
});

it('recounts every model after views were pruned under the database source', function (): void {
    recountChanged();

    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);

    expect(recountChanged()->models)->toBe([Post::class => 3])
        ->and(columnsOf($this->post))->toBe([[3, 2]]);
});

it('recounts nothing after views were pruned under the rollup source', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    app(FoldViews::class)->handle();
    recountChanged();

    app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);

    expect(recountChanged()->models)->toBe([Post::class => 0])
        ->and(columnsOf($this->post))->toBe([[4, 2]]);
});

it('finds the models whose pruned views left the period through the rollups', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    $solo = Post::query()->create(['title' => 'Solo', 'body' => 'Body']);
    View::factory()->for($solo, 'viewable')->viewedAt(Carbon::parse('2026-03-27 10:00:00'))->fromVisitor('visitor-3')->create();

    app(FoldViews::class)->handle();
    app(PruneViews::class)->handle(Carbon::parse('2026-03-28'), 100);
    recountChanged();

    expect(columnsOf($solo))->toBe([[1, 1]]);

    $this->travelTo(Carbon::parse('2026-04-04 12:00:00'));

    recountChanged();

    expect(columnsOf($solo))->toBe([[1, 0]]);
});

it('recounts every model once the columns change', function (): void {
    recountChanged();

    config()->set('eloquent-viewable.querying.counters', [Post::class => [
        'cached_views',
        'cached_unique_views' => ['unique' => true, 'period' => '30d'],
    ]]);

    expect(recountChanged()->models)->toBe([Post::class => 3]);
});

it('recounts every model after bot views were purged', function (): void {
    recountChanged();

    event(new BotViewsPurged(Carbon::parse('2026-03-30'), Carbon::parse('2026-03-31'), 1, 1));

    expect(recountChanged()->models)->toBe([Post::class => 3]);
});

it('recounts every model on request', function (): void {
    recountChanged();

    expect(recountChanged(full: true)->models)->toBe([Post::class => 3]);
});

it('stops at the deadline and finishes the same recount on the next run', function (): void {
    recountChanged();

    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-03-31 09:00:00'))->fromVisitor('visitor-2')->create();
    View::factory()->for($this->unseen, 'viewable')->viewedAt(Carbon::parse('2026-03-31 09:00:00'))->create();

    $first = recountChanged(chunk: 1, deadline: deadlineAfter(1));

    expect($first->models)->toBe([Post::class => 1])
        ->and($first->stopped)->toBeTrue()
        ->and(columnsOf($this->post, $this->unseen))->toBe([[5, 2], [0, 0]]);

    $second = recountChanged(chunk: 1);

    expect($second->models)->toBe([Post::class => 1])
        ->and($second->stopped)->toBeFalse()
        ->and(columnsOf($this->unseen))->toBe([[1, 1]])
        ->and(recountChanged()->models)->toBe([Post::class => 0]);
});

it('resumes a recount of every model after the last key it wrote', function (): void {
    $first = recountChanged(chunk: 2, deadline: deadlineAfter(1));

    expect($first->models)->toBe([Post::class => 2])
        ->and($first->stopped)->toBeTrue()
        ->and(columnsOf($this->post, $this->other))->toBe([[4, 2], [1, 1]])
        ->and(recountChanged(chunk: 2)->models)->toBe([Post::class => 1]);
});

it('recounts every model and ignores the deadline without the state table', function (): void {
    app()->instance(StateStore::class, new class implements StateStore
    {
        public function installed(): bool
        {
            return false;
        }

        public function many(array $names): array
        {
            return [];
        }

        public function get(string $name): ?string
        {
            return null;
        }

        public function put(string $name, string $value): void {}

        public function forget(string $name): void {}
    });

    $run = recountChanged(chunk: 1, deadline: deadlineAfter(0));

    expect($run->models)->toBe([Post::class => 3])
        ->and($run->stopped)->toBeFalse()
        ->and(columnsOf($this->post))->toBe([[4, 2]]);

    views(Post::class)->destroy();
    event(new BotViewsPurged(Carbon::parse('2026-03-30'), Carbon::parse('2026-03-31'), 1, 1));

    expect(recountChanged()->models)->toBe([Post::class => 3]);
});

it('recounts every model through a source other than the shipped ones', function (): void {
    $database = app(DatabaseSource::class);

    app()->instance(ViewSource::class, new readonly class($database) implements SubquerySource, ViewSource
    {
        public function __construct(private DatabaseSource $database) {}

        public function count(Viewable $viewable, ViewsQuery $query): int
        {
            return $this->database->count($viewable, $query);
        }

        public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
        {
            return $this->database->countByInterval($viewable, $query, $granularity);
        }

        public function countByCollection(Viewable $viewable, ViewsQuery $query): array
        {
            return $this->database->countByCollection($viewable, $query);
        }

        public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
        {
            return $this->database->countMany($viewable, $keys, $query);
        }

        public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
        {
            return $this->database->top($viewable, $query, $limit);
        }

        public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
        {
            return $this->database->countSubquery($viewable, $query);
        }

        public function viewsSubquery(Viewable $viewable, ViewsQuery $query, ?string $visitor = null): Builder
        {
            return $this->database->viewsSubquery($viewable, $query, $visitor);
        }
    });

    recountChanged();

    expect(recountChanged()->models)->toBe([Post::class => 3]);
});

it('recounts a model right away when its views are destroyed', function (): void {
    recountChanged();

    views($this->post)->destroy();

    expect(columnsOf($this->post, $this->other))->toBe([[0, 0], [1, 1]]);
});

it('recounts every model on the next run once the views of the whole type are destroyed', function (): void {
    recountChanged();

    views(Post::class)->destroy();

    expect(recountChanged()->models)->toBe([Post::class => 3])
        ->and(columnsOf($this->post))->toBe([[0, 0]]);
});

it('recounts nothing once the rollups hold every view', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    app(FoldViews::class)->handle();
    recountChanged();

    app(PruneViews::class)->handle(Carbon::parse('2026-03-31'), 100);

    expect(View::query()->count())->toBe(0)
        ->and(recountChanged()->models)->toBe([Post::class => 0])
        ->and(columnsOf($this->post))->toBe([[4, 2]]);
});
