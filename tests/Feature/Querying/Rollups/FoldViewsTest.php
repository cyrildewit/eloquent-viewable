<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Events\ViewsRolledUp;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\RollupsNotInstalled;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Retention\Actions\AnonymiseViews;
use CyrildeWit\EloquentViewable\Retention\State\RetentionState;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    $this->post = Post::factory()->create();
    $this->other = Post::factory()->create();
});

function viewAt(Post $post, string $viewedAt, string $visitor = 'visitor-1', ?string $collection = null): View
{
    return View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->fromVisitor($visitor)->inCollection($collection)->create();
}

/** @return array{0: int, 1: int}|null */
function bucket(string $tier, string $start, string $grouping, ?Post $post = null, ?string $collection = null, string $type = Post::class): ?array
{
    $row = ViewRollup::query()
        ->where('tier', $tier)
        ->where('bucket_start', Carbon::parse($start))
        ->where('grouping', $grouping)
        ->where('viewable_type', new Post()->getMorphClass())
        ->when($post, fn ($query) => $query->where('viewable_id', $post?->getKey()), fn ($query) => $query->whereNull('viewable_id'))
        ->where('collection', $collection)
        ->first();

    return $row === null ? null : [(int) $row->views, (int) $row->unique_visitors];
}

/** @return list<ViewsRolledUp> */
function fold(?Tier $tier = null, ?string $from = null, bool $dryRun = false): array
{
    return app(FoldViews::class)->handle($tier, $from === null ? null : Carbon::parse($from), $dryRun);
}

it('folds the views of each closed bucket per grouping', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-01-10 11:00:00');
    viewAt($this->post, '2026-01-11 09:00:00', 'visitor-2', 'featured');
    viewAt($this->other, '2026-01-10 12:00:00');

    fold();

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([2, 1])
        ->and(bucket('day', '2026-01-10', 'viewable', $this->other))->toBe([1, 1])
        ->and(bucket('day', '2026-01-10', 'type'))->toBe([3, 1])
        ->and(bucket('day', '2026-01-11', 'viewable_collection', $this->post, 'featured'))->toBe([1, 1])
        ->and(bucket('day', '2026-01-11', 'viewable_collection', $this->post))->toBeNull()
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([3, 2])
        ->and(bucket('month', '2026-01-01', 'type'))->toBe([4, 2])
        ->and(bucket('month', '2026-01-01', 'type_collection'))->toBeNull();
});

it('folds a grouping only when it is kept', function (): void {
    config()->set('eloquent-viewable.retention.rollups.groupings', ['type_collection']);

    viewAt($this->post, '2026-01-10 10:00:00', collection: 'featured');

    fold();

    expect(ViewRollup::query()->distinct()->pluck('grouping')->all())->toBe(['type_collection'])
        ->and(bucket('day', '2026-01-10', 'type_collection', collection: 'featured'))->toBe([1, 1]);
});

it('waits for a bucket to settle before folding it', function (): void {
    viewAt($this->post, '2026-03-30 23:30:00');
    $this->travelTo(Carbon::parse('2026-03-31 00:30:00'));

    $runs = fold();

    expect(ViewRollup::query()->count())->toBe(0)
        ->and($runs[1]->until->toDateTimeString())->toBe('2026-03-30 00:00:00');

    $this->travelTo(Carbon::parse('2026-03-31 01:00:00'));
    fold();

    expect(bucket('day', '2026-03-30', 'viewable', $this->post))->toBe([1, 1]);
});

it('folds closed buckets at once without a settle', function (): void {
    config()->set('eloquent-viewable.retention.rollups.settle');

    viewAt($this->post, '2026-03-30 23:30:00');
    $this->travelTo(Carbon::parse('2026-03-31 00:00:00'));

    fold();

    expect(bucket('day', '2026-03-30', 'viewable', $this->post))->toBe([1, 1]);
});

it('marks how far each tier is folded and where it starts', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');

    $runs = fold();
    $snapshot = app(RollupState::class)->snapshot('views');

    expect($snapshot->folded(Tier::Day)?->toDateTimeString())->toBe('2026-03-31 00:00:00')
        ->and($snapshot->folded(Tier::Month)?->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and($snapshot->since(Tier::Day)?->toDateTimeString())->toBe('2026-01-10 00:00:00')
        ->and($snapshot->since(Tier::Month)?->toDateTimeString())->toBe('2026-01-01 00:00:00')
        ->and($snapshot->origin?->toDateTimeString())->toBe('2026-01-01 00:00:00')
        ->and(array_map(fn (ViewsRolledUp $run): array => [$run->tier, $run->from, $run->buckets], $runs))->toBe([
            [Tier::Month, null, 1],
            [Tier::Day, null, 1],
        ]);
});

it('marks a tier folded when there is nothing to fold', function (): void {
    fold();

    expect(app(RollupState::class)->snapshot('views')->folded(Tier::Day)?->toDateTimeString())->toBe('2026-03-31 00:00:00')
        ->and(app(RollupState::class)->snapshot('views')->since(Tier::Day)?->toDateTimeString())->toBe('2026-03-31 00:00:00');
});

it('folds what is new after a run over no views at all', function (): void {
    fold();

    viewAt($this->post, '2026-03-31 10:00:00');
    $this->travelTo(Carbon::parse('2026-04-01 12:00:00'));

    fold();

    expect(bucket('day', '2026-03-31', 'viewable', $this->post))->toBe([1, 1]);
});

it('folds only what is new on the next run', function (): void {
    viewAt($this->post, '2026-03-29 10:00:00');
    fold();

    viewAt($this->post, '2026-03-31 10:00:00');
    $this->travelTo(Carbon::parse('2026-04-01 12:00:00'));

    $runs = fold(Tier::Day);

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->from?->toDateTimeString())->toBe('2026-03-31 00:00:00')
        ->and($runs[0]->buckets)->toBe(1)
        ->and(bucket('day', '2026-03-31', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('day', '2026-03-29', 'viewable', $this->post))->toBe([1, 1]);
});

it('folds a bucket again when a view lands in it late', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    fold();

    viewAt($this->post, '2026-01-10 18:00:00', 'visitor-2');
    $runs = fold();

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([2, 2])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([2, 2])
        ->and($runs[1]->buckets)->toBe(1)
        ->and(ViewRollup::query()->where('tier', 'day')->where('grouping', 'viewable')->count())->toBe(1);
});

it('keeps a late view for the tiers a run of one tier left out', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    fold();

    viewAt($this->post, '2026-01-10 18:00:00', 'visitor-2');
    fold(Tier::Day);

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([2, 2])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([1, 1]);

    fold();

    expect(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([2, 2]);
});

it('leaves a bucket alone when views before it are pruned', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-01-20 10:00:00');
    fold();

    app(RetentionState::class)->put('pruned', '2026-01-15 00:00:00');
    View::query()->where('viewed_at', '<', '2026-01-15')->delete();
    viewAt($this->post, '2026-01-10 18:00:00');
    viewAt($this->post, '2026-01-20 18:00:00', 'visitor-2');

    fold();

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('day', '2026-01-20', 'viewable', $this->post))->toBe([2, 2])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([2, 1]);
});

it('leaves a month alone that anonymising reached, but folds its days again', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    fold();

    app(RetentionState::class)->put('anonymised', '2026-02-01 00:00:00');
    viewAt($this->post, '2026-01-10 18:00:00', 'visitor-2');

    fold();

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([2, 2])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([1, 1]);
});

it('folds anonymised days again with the same unique visitors, on the rollup clock across daylight saving time', function (): void {
    config()->set('eloquent-viewable.retention.rollups.timezone', 'Europe/Amsterdam');
    $this->travelTo(Carbon::parse('2026-04-15 12:00:00'));

    // 29 March is 23 hours long in Amsterdam, from 2026-03-28 23:00 to 2026-03-29 22:00 UTC.
    $first = viewAt($this->post, '2026-03-28 23:30:00');
    $last = viewAt($this->post, '2026-03-29 21:30:00');
    $nextDay = viewAt($this->post, '2026-03-29 22:30:00');

    fold();

    $run = app(AnonymiseViews::class)->handle(Carbon::parse('2026-04-01'), ['visitor', 'viewer', 'context'], 100);

    fold(from: '2026-03-01');

    expect($run->until->toDateTimeString())->toBe('2026-03-31 22:00:00')
        ->and($first->refresh()->visitor)->toStartWith('a:')->toBe($last->refresh()->visitor)->not->toBe($nextDay->refresh()->visitor)
        ->and(bucket('day', '2026-03-28 23:00:00', 'viewable', $this->post))->toBe([2, 1])
        ->and(bucket('day', '2026-03-29 22:00:00', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('month', '2026-02-28 23:00:00', 'viewable', $this->post))->toBe([3, 1]);
});

it('folds again from a given date', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    fold();

    ViewRollup::query()->delete();

    $runs = fold(from: '2026-01-05');

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBe([1, 1])
        ->and($runs[1]->buckets)->toBe(1);
});

it('folds again no further back than the views are all still there', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-01-20 10:00:00');
    fold();

    app(RetentionState::class)->put('pruned', '2026-01-15 00:00:00');
    View::query()->where('viewed_at', '<', '2026-01-15')->delete();

    fold(Tier::Day, from: '2026-01-01');

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([1, 1]);
});

it('folds again from a given date after the views are pruned', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-01-20 10:00:00');
    fold();

    app(RetentionState::class)->put('pruned', '2026-01-15 00:00:00');
    View::query()->where('viewed_at', '<', '2026-01-15')->delete();
    ViewRollup::query()->delete();

    $runs = fold(Tier::Day, from: '2026-01-20');

    expect(bucket('day', '2026-01-20', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('day', '2026-01-10', 'viewable', $this->post))->toBeNull()
        ->and($runs[0]->buckets)->toBe(1);
});

it('folds a month again no further back than both anonymising and pruning reached', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-02-10 10:00:00');
    fold();

    app(RetentionState::class)->put('pruned', '2026-01-15 00:00:00');
    app(RetentionState::class)->put('anonymised', '2026-02-01 00:00:00');
    View::query()->where('viewed_at', '<', '2026-01-15')->delete();
    ViewRollup::query()->delete();

    fold(Tier::Month, from: '2026-01-01');

    expect(bucket('month', '2026-02-01', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('month', '2026-01-01', 'viewable', $this->post))->toBeNull();
});

it('aligns buckets to the configured timezone', function (): void {
    config()->set('eloquent-viewable.retention.rollups.timezone', 'Europe/Amsterdam');

    viewAt($this->post, '2026-01-09 23:30:00');
    viewAt($this->post, '2026-01-10 22:30:00');

    fold();

    expect(bucket('day', '2026-01-09 23:00:00', 'viewable', $this->post))->toBe([2, 1]);
});

it('counts the buckets without folding them on a dry run', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-02-10 10:00:00');

    $runs = fold(dryRun: true);

    expect(array_map(fn (ViewsRolledUp $run): int => $run->buckets, $runs))->toBe([2, 2])
        ->and(ViewRollup::query()->count())->toBe(0)
        ->and(app(RollupState::class)->snapshot('views')->folded(Tier::Day))->toBeNull()
        ->and(app(RollupState::class)->lastId())->toBeNull();
});

it('dispatches an event per tier that folded something', function (): void {
    Event::fake([ViewsRolledUp::class]);

    viewAt($this->post, '2026-03-30 10:00:00');

    fold();

    Event::assertDispatchedTimes(ViewsRolledUp::class, 1);
    Event::assertDispatched(ViewsRolledUp::class, fn (ViewsRolledUp $event): bool => $event->tier === Tier::Day && $event->buckets === 1);
});

it('forgets the remembered counts once it folded', function (): void {
    viewAt($this->post, '2026-03-30 10:00:00');
    expect(views($this->post)->remember(3600)->count())->toBe(1);

    viewAt($this->post, '2026-03-30 11:00:00');
    expect(views($this->post)->remember(3600)->count())->toBe(1);

    fold();

    expect(views($this->post)->remember(3600)->count())->toBe(2);
});

it('throws when the rollup table is missing', function (): void {
    config()->set('eloquent-viewable.retention.rollups.table', 'missing_rollups');

    fold();
})->throws(RollupsNotInstalled::class, 'The `missing_rollups` table does not exist. Publish its migration with `php artisan vendor:publish --tag=eloquent-viewable-rollups` and run `php artisan migrate`.');

it('throws when the state table is missing', function (): void {
    config()->set('database.connections.bare', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('eloquent-viewable.models.view.connection', 'bare');
    app('db')->connection('bare')->getSchemaBuilder()->create('view_rollups', fn ($table) => $table->id());

    fold();
})->throws(RollupsNotInstalled::class, 'The `view_retention_state` table does not exist.');

it('stops before the next bucket at the deadline and carries on from there', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    viewAt($this->post, '2026-01-11 10:00:00');
    viewAt($this->other, '2026-01-12 10:00:00');

    $runs = app(FoldViews::class)->handle(deadline: deadlineAfter(2));

    expect($runs)->toHaveCount(2)
        ->and($runs[0]->tier)->toBe(Tier::Month)
        ->and($runs[0]->stopped)->toBeFalse()
        ->and($runs[1]->tier)->toBe(Tier::Day)
        ->and($runs[1]->stopped)->toBeTrue()
        ->and($runs[1]->buckets)->toBe(1)
        ->and($runs[1]->until->toDateTimeString())->toBe('2026-01-11 00:00:00')
        ->and(app(RollupState::class)->snapshot('views')->folded(Tier::Day)?->toDateTimeString())->toBe('2026-01-11 00:00:00')
        ->and(app(RollupState::class)->lastId())->toBeNull();

    $rest = fold();

    expect(array_map(fn (ViewsRolledUp $run): bool => $run->stopped, $rest))->toBe([false, false])
        ->and(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('day', '2026-01-11', 'viewable', $this->post))->toBe([1, 1])
        ->and(bucket('day', '2026-01-12', 'viewable', $this->other))->toBe([1, 1])
        ->and(bucket('month', '2026-01-01', 'type'))->toBe([3, 1])
        ->and(app(RollupState::class)->lastId())->not->toBeNull();
});

it('leaves a late view for the next run when the deadline passes first', function (): void {
    viewAt($this->post, '2026-01-10 10:00:00');
    fold();

    $lastId = app(RollupState::class)->lastId();
    viewAt($this->post, '2026-01-10 11:00:00');

    $runs = app(FoldViews::class)->handle(deadline: deadlineAfter(0));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->tier)->toBe(Tier::Month)
        ->and($runs[0]->stopped)->toBeTrue()
        ->and($runs[0]->buckets)->toBe(0)
        ->and($runs[0]->until->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([1, 1])
        ->and(app(RollupState::class)->lastId())->toBe($lastId);

    fold();

    expect(bucket('day', '2026-01-10', 'viewable', $this->post))->toBe([2, 1]);
});
