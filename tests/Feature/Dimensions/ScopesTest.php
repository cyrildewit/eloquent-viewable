<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\Exceptions\UnknownDimension;
use CyrildeWit\EloquentViewable\Dimensions\Source;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Retention\Actions\PruneViews;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Dimensions\PlanDimension;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;

/**
 * @param  array<string, ?string>  $dimensions
 * @param  array<string, mixed>|null  $context
 */
function scopedView(Post $post, array $dimensions, string $visitor, ?User $viewer = null, ?array $context = null, string $at = '2026-03-30 10:00:00'): void
{
    $factory = View::factory()
        ->for($post, 'viewable')
        ->withDimensions($dimensions)
        ->withContext($context)
        ->fromVisitor($visitor)
        ->viewedAt(Carbon::parse($at));

    ($viewer instanceof User ? $factory->by($viewer) : $factory)->create();
}

/**
 * The first post is viewed most from Bing, the second most from Google, and
 * only the second on a phone.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.dimensions.definitions', [
        'source' => Source::class,
        'device' => Device::class,
        'plan' => PlanDimension::class,
    ]);

    $this->viewer = User::factory()->create();
    $this->first = Post::factory()->create();
    $this->second = Post::factory()->create();

    scopedView($this->first, ['source' => 'Bing', 'device' => 'desktop'], 'visitor-1', $this->viewer);
    scopedView($this->first, ['source' => 'Bing', 'device' => 'desktop'], 'visitor-2');
    scopedView($this->first, ['source' => 'Bing', 'device' => 'desktop'], 'visitor-2');
    scopedView($this->first, ['source' => 'Google', 'device' => 'desktop'], 'visitor-3', context: ['plan' => 'pro']);
    scopedView($this->second, ['source' => 'Google', 'device' => 'mobile'], 'visitor-1', context: ['plan' => 'pro']);
    scopedView($this->second, ['source' => 'Google', 'device' => 'mobile'], 'visitor-4', context: ['plan' => 'pro']);
    scopedView($this->second, ['source' => 'Bing', 'device' => 'desktop'], 'visitor-5');
});

it('orders by the views of the dimension values', function (): void {
    expect(Post::query()->orderByViews()->pluck('id')->all())->toEqual(keysOf($this->first, $this->second)->all())
        ->and(Post::query()->orderByViews(dimensions: ['source' => 'Google'])->pluck('id')->all())->toEqual(keysOf($this->second, $this->first)->all())
        ->and(Post::query()->orderByUniqueViews(dimensions: ['source' => 'Bing'])->pluck('id')->all())->toEqual(keysOf($this->first, $this->second)->all());
});

it('selects the views count of the dimension values', function (): void {
    $counts = fn (array $dimensions): array => Post::query()->withViewsCount(dimensions: $dimensions)->orderBy('id')->pluck('views_count')->map(fn (mixed $count): int => (int) $count)->all();

    expect($counts(['source' => 'Google']))->toBe([1, 2])
        ->and($counts(['source' => ['Google', 'Bing']]))->toBe([4, 3])
        ->and($counts(['source' => 'Google', 'device' => 'mobile']))->toBe([0, 2])
        ->and($counts(['plan' => 'pro']))->toBe([1, 2]);
});

it('filters by the views count of the dimension values', function (): void {
    expect(Post::query()->whereViewsCount('>=', 2, dimensions: ['source' => 'Google'])->pluck('id')->all())->toEqual(keysOf($this->second)->all())
        ->and(Post::query()->whereUniqueViewsCount('>=', 2, dimensions: ['source' => 'Bing'])->pluck('id')->all())->toEqual(keysOf($this->first)->all());
});

it('ranks by trending within the dimension values', function (): void {
    expect(Post::query()->orderByTrending(dimensions: ['device' => 'mobile'])->pluck('id')->first())->toEqual($this->second->getKey())
        ->and((float) Post::query()->withTrendingScore(dimensions: ['device' => 'mobile'])->whereKey($this->first->getKey())->value('trending_score'))->toBe(0.0);
});

it('narrows what a viewer and a visitor viewed', function (): void {
    expect(Post::query()->whereViewedBy($this->viewer, dimensions: ['source' => 'Bing'])->pluck('id')->all())->toEqual(keysOf($this->first)->all())
        ->and(Post::query()->whereViewedBy($this->viewer, dimensions: ['source' => 'Google'])->exists())->toBeFalse()
        ->and(Post::query()->whereNotViewedBy($this->viewer, dimensions: ['source' => 'Google'])->count())->toBe(2)
        ->and(Post::query()->whereViewedByVisitor('visitor-1', dimensions: ['device' => 'mobile'])->pluck('id')->all())->toEqual(keysOf($this->second)->all())
        ->and(Post::query()->whereNotViewedByVisitor('visitor-1', dimensions: ['device' => 'mobile'])->pluck('id')->all())->toEqual(keysOf($this->first)->all());
});

it('recommends from the views of the dimension values', function (): void {
    config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);

    $third = Post::factory()->create();
    scopedView($third, ['source' => 'Bing'], 'visitor-2');
    scopedView($this->second, ['source' => 'Bing'], 'visitor-2');
    scopedView($this->first, ['source' => 'Google'], 'visitor-9');
    scopedView($this->second, ['source' => 'Google'], 'visitor-9');

    expect(Post::query()->recommendedFor('visitor-3', includeSeen: true)->pluck('id')->sort()->values()->all())->toEqual(keysOf($this->second, $third)->all())
        ->and(Post::query()->recommendedFor('visitor-3', includeSeen: true, dimensions: ['source' => 'Google'])->pluck('id')->all())->toEqual(keysOf($this->second)->all());
});

it('refuses a dimension that is not in config', function (): void {
    Post::query()->orderByViews(dimensions: ['browser' => 'Firefox']);
})->throws(UnknownDimension::class, 'No dimension is named `browser`.');

describe('the rollup source', function (): void {
    beforeEach(function (): void {
        config()->set('eloquent-viewable.querying.source.driver', 'rollup');
        config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null, 'month' => null]);
        config()->set('eloquent-viewable.retention.rollups.dimensions', ['source']);

        scopedView($this->second, ['source' => 'Google', 'device' => 'mobile'], 'visitor-6', at: '2026-01-10 10:00:00');

        app(FoldViews::class)->handle();
        app(PruneViews::class)->handle(Carbon::parse('2026-03-01'), 100);
    });

    it('reads the history of one folded dimension from its rollup', function (): void {
        expect(Post::query()->withViewsCount(dimensions: ['source' => 'Google'])->orderBy('id')->pluck('views_count')->map(fn (mixed $count): int => (int) $count)->all())->toBe([1, 3])
            ->and(Post::query()->whereViewsCount('>', 2, Period::since('2026-01-01'), dimensions: ['source' => 'Google'])->pluck('id')->all())->toEqual(keysOf($this->second)->all());
    });

    it('refuses two dimensions once their views are pruned', function (): void {
        Post::query()->withViewsCount(dimensions: ['source' => 'Google', 'device' => 'mobile'])->get();
    })->throws(UnsupportedBySource::class, 'A count by `source` and `device` reads the views table');
});
