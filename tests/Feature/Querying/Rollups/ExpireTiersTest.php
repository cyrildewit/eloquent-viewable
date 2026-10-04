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

/** @return list<string> */
function bucketsOf(string $tier): array
{
    return ViewRollup::query()->where('tier', $tier)->where('grouping', 'type')->orderBy('bucket_start')->pluck('bucket_start')
        ->map(fn (string $start): string => substr($start, 0, 10))->all();
}

it('drops the buckets of a tier older than it is kept, in whole buckets of the next coarser tier', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);
    app(FoldViews::class)->handle();

    $dropped = app(ExpireTiers::class)->handle(chunk: 1);

    expect($dropped)->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 3]])
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
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => '1y']);
    app(FoldViews::class)->handle();

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBe([['rollup' => 'views', 'tier' => Tier::Month, 'rows' => 6]])
        ->and(bucketsOf('month'))->toBe(['2025-03-01']);
});

it('waits for a coarser tier that has never been folded', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1d']);
    app(FoldViews::class)->handle();
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '1d', 'month' => null]);

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBeEmpty();
});

it('does nothing a second time', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);
    app(FoldViews::class)->handle();
    app(ExpireTiers::class)->handle(chunk: 100);

    expect(app(ExpireTiers::class)->handle(chunk: 100))->toBeEmpty();
});

it('counts the rows it would drop on a dry run', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '400d', 'month' => null]);
    app(FoldViews::class)->handle();

    expect(app(ExpireTiers::class)->handle(chunk: 100, dryRun: true))->toBe([['rollup' => 'views', 'tier' => Tier::Day, 'rows' => 3]])
        ->and(bucketsOf('day'))->toHaveCount(4);
});
