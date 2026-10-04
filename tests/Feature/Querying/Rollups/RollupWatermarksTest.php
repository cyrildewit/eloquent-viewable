<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\NullWatermarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupWatermarks;
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
