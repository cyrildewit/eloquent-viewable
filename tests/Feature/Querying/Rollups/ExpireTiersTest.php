<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\ExpireTiers;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    $post = Post::factory()->create();

    foreach (['2025-01-15', '2025-02-15', '2025-03-15', '2026-03-15'] as $viewedAt) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();
    }
});

/**
 * Folds every tier kept for good, then keeps them as given, as when a keep is
 * shortened after the tiers were folded.
 *
 * @param  array<string, ?string>  $tiers
 */
function foldThenKeep(array $tiers): void
{
    config()->set('eloquent-viewable.retention.rollups.tiers', array_map(fn (): null => null, $tiers));
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', $tiers);
}

/** @return list<string> */
function bucketsOf(string $tier): array
{
    return ViewRollup::query()->where('tier', $tier)->where('grouping', 'type')->orderBy('bucket_start')->pluck('bucket_start')
        ->map(fn (string $start): string => substr($start, 0, 10))->all();
}

it('drops the buckets of a tier older than it is kept, in whole buckets of the next coarser tier', function (): void {
    foldThenKeep(['day' => '400d', 'month' => null]);

    $dropped = app(ExpireTiers::class)->handle(chunk: 1);

    expect($dropped)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 3, 'stopped' => false, 'removed' => false]])
        ->and(bucketsOf('day'))->toBe(['2025-02-15', '2025-03-15', '2026-03-15'])
        ->and(bucketsOf('month'))->toHaveCount(3)
        ->and(app(RollupState::class)->snapshot('views')->since(Tier::Day)?->toDateTimeString())->toBe('2025-02-01 00:00:00');
});

it('keeps buckets the coarser tier has not folded yet', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1d', 'month' => null]);
    app(FoldViews::class)->handle();

    app(ExpireTiers::class)->handle(chunk: 100);

    expect(bucketsOf('day'))->toBe(['2026-03-15']);
});

it('drops the coarsest tier on its own grain', function (): void {
    foldThenKeep(['month' => '1y']);

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBe([['rollup' => 'views', 'tier' => Tier::Month, 'rows' => 6, 'stopped' => false, 'removed' => false]])
        ->and(bucketsOf('month'))->toBe(['2025-03-01']);
});

it('waits for a coarser tier that has never been folded', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1d']);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1d', 'month' => null]);

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBeEmpty();
});

it('does nothing a second time', function (): void {
    foldThenKeep(['day' => '400d', 'month' => null]);
    app(ExpireTiers::class)->handle(chunk: 100);

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBeEmpty();
});

it('counts the rows it would drop on a dry run', function (): void {
    foldThenKeep(['day' => '400d', 'month' => null]);

    expect(app(ExpireTiers::class)->handle(chunk: 100, dryRun: true))->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 3, 'stopped' => false, 'removed' => false]])
        ->and(bucketsOf('day'))->toHaveCount(4);
});

it('stops at the deadline and leaves the rest of the tier for the next run', function (): void {
    foldThenKeep(['day' => '400d', 'month' => null]);

    $since = app(RollupState::class)->snapshot('views')->since(Tier::Day)?->toDateTimeString();
    $dropped = app(ExpireTiers::class)->handle(chunk: 1, deadline: deadlineAfter(1));

    expect($dropped)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 1, 'stopped' => true, 'removed' => false]])
        ->and(app(RollupState::class)->snapshot('views')->since(Tier::Day)?->toDateTimeString())->toBe($since)
        ->and(app(ExpireTiers::class)->handle(chunk: 1))->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 2, 'stopped' => false, 'removed' => false]])
        ->and(bucketsOf('day'))->toBe(['2025-02-15', '2025-03-15', '2026-03-15']);
});

it('drops a tier the rollup no longer keeps, with its marks', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);

    $dropped = app(ExpireTiers::class)->handle(chunk: 1);
    $snapshot = app(RollupState::class)->snapshot('views');

    expect($dropped)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 12, 'stopped' => false, 'removed' => true]])
        ->and(bucketsOf('day'))->toBeEmpty()
        ->and(bucketsOf('month'))->toHaveCount(3)
        ->and($snapshot->folded(Tier::Day))->toBeNull()
        ->and($snapshot->since(Tier::Day))->toBeNull()
        ->and(app(ExpireTiers::class)->handle(chunk: 100))->toBeEmpty();
});

it('counts the rows of a tier no longer kept on a dry run', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);

    expect(app(ExpireTiers::class)->handle(chunk: 100, dryRun: true))->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 12, 'stopped' => false, 'removed' => true]])
        ->and(bucketsOf('day'))->toHaveCount(4)
        ->and(app(RollupState::class)->snapshot('views')->folded(Tier::Day))->not->toBeNull();
});

it('keeps the marks of a tier no longer kept until every row is dropped', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);

    $dropped = app(ExpireTiers::class)->handle(chunk: 1, deadline: deadlineAfter(1));

    expect($dropped)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 1, 'stopped' => true, 'removed' => true]])
        ->and(app(RollupState::class)->snapshot('views')->folded(Tier::Day))->not->toBeNull()
        ->and(app(ExpireTiers::class)->handle(chunk: 100))->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 11, 'stopped' => false, 'removed' => true]]);
});

it('folds a tier kept again as if for the first time', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);
    app(ExpireTiers::class)->handle(chunk: 100);

    $this->travelTo(Carbon::parse('2026-05-31 12:00:00'));
    View::factory()->for(Post::query()->firstOrFail(), 'viewable')->viewedAt(Carbon::parse('2026-04-15'))->create();
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();

    expect(bucketsOf('day'))->toBe(['2025-01-15', '2025-02-15', '2025-03-15', '2026-03-15', '2026-04-15']);
});
