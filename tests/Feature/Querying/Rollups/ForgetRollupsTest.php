<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Rollups\Actions\FoldViews;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));

    config()->set('eloquent-viewable.retention.rollups.tiers', ['day' => null]);

    $this->post = Post::factory()->create();
    $this->other = Post::factory()->create();

    foreach ([$this->post, $this->other] as $post) {
        View::factory()->for($post, 'viewable')->viewedAt(Carbon::parse('2026-03-01'))->inCollection('featured')->create();
    }

    app(FoldViews::class)->handle();
});

it('removes the rollup rows of a model whose views are destroyed, but not its share of the type', function (): void {
    views($this->post)->destroy();

    expect(ViewRollup::query()->where('viewable_id', $this->post->getKey())->count())->toBe(0)
        ->and(ViewRollup::query()->where('viewable_id', $this->other->getKey())->count())->toBe(2)
        ->and(ViewRollup::query()->where('grouping', 'type')->value('views'))->toBe(2);
});

it('removes every rollup row of a type whose views are destroyed', function (): void {
    views(new Post)->destroy();

    expect(ViewRollup::query()->count())->toBe(0);
});

it('leaves the rollup table alone while no tier is configured', function (): void {
    config()->set('eloquent-viewable.retention.rollups.tiers', []);

    views($this->post)->destroy();

    expect(ViewRollup::query()->count())->toBe(5);
});

it('does nothing when the rollup table is missing', function (): void {
    config()->set('eloquent-viewable.retention.rollups.table', 'missing_rollups');

    views($this->post)->destroy();

    expect(View::query()->count())->toBe(1);
});
