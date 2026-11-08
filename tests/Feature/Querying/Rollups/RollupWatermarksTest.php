<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\NullWatermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupState;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupWatermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));
});

it('binds the null watermarks while no tier is configured', function (): void {
    expect(app(Watermarks::class))->toBeInstanceOf(NullWatermarks::class);
});

it('clamps a cutoff to the tier folded least far', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
    app(FoldViews::class)->handle();

    $watermarks = app(Watermarks::class);

    expect($watermarks)->toBeInstanceOf(RollupWatermarks::class)
        ->and($watermarks->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and($watermarks->clamp(Carbon::parse('2026-02-01'))->toDateTimeString())->toBe('2026-02-01 00:00:00');
});

it('clamps every cutoff to the epoch before the first fold', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-20'))->getTimestamp())->toBe(0);
});

it('clamps a cutoff to where a run would fold every tier up to now', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    expect(app(Watermarks::class)->afterFolding()->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2026-03-01 00:00:00');

    app(FoldViews::class)->handle();
    $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-05-10'))->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and(app(Watermarks::class)->afterFolding()->clamp(Carbon::parse('2026-05-10'))->toDateTimeString())->toBe('2026-05-01 00:00:00');
});

it('keeps a tier folded further than a run would fold it now', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['month' => null]);

    $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));
    app(FoldViews::class)->handle();
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    expect(app(Watermarks::class)->afterFolding()->clamp(Carbon::parse('2026-06-01'))->toDateTimeString())->toBe('2026-05-01 00:00:00');
});

it('clamps a cutoff to the month of the oldest view a rollup should count but does not', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);

    $post = Post::factory()->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2025-06-10 10:00:00'))->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2026-02-10 10:00:00'))->create();
    app(FoldViews::class)->handle();

    $state = app(RollupState::class);
    $state->putSince('views', Tier::Day, Carbon::parse('2026-02-10'));
    $state->putSince('views', Tier::Month, Carbon::parse('2026-02-01'));

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2025-06-01 00:00:00')
        ->and(app(Watermarks::class)->clamp(Carbon::parse('2025-05-01'))->toDateTimeString())->toBe('2025-05-01 00:00:00');
});

it('clamps a cutoff no earlier than the views every tier has expired', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => '90d', 'month' => '1y']);

    $post = Post::factory()->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2024-06-10 10:00:00'))->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2025-05-10 10:00:00'))->create();
    View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2026-02-10 10:00:00'))->create();
    app(FoldViews::class)->handle();

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2026-03-01 00:00:00');

    $state = app(RollupState::class);
    $state->putSince('views', Tier::Day, Carbon::parse('2026-02-01'));
    $state->putSince('views', Tier::Month, Carbon::parse('2026-02-01'));

    expect(app(Watermarks::class)->clamp(Carbon::parse('2026-03-20'))->toDateTimeString())->toBe('2025-05-01 00:00:00');
});
