<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('has a views relationship', function (): void {
    expect($this->post->views())->toBeInstanceOf(MorphMany::class);
});

it('can be ordered by views in descending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    View::factory()->for($postOne, 'viewable')->count(4)->create();

    View::factory()->for($postTwo, 'viewable')->create();

    View::factory()->for($postThree, 'viewable')->count(2)->create();

    View::factory()->for($postFour, 'viewable')->count(3)->create();

    expect(Post::orderByViews()->pluck('id'))->toEqual(keysOf($postOne, $postFour, $postThree, $postTwo));
});

it('can be ordered by unique views in descending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // The unique order must differ from the total order, otherwise this test
    // cannot tell orderByUniqueViews apart from orderByViews.

    // Unique views: 1, total views: 6
    View::factory()->for($postOne, 'viewable')->fromVisitor('visitor_one')->count(6)->create();

    // Unique views: 2, total views: 3
    View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
    View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->count(2)->create();

    // Unique views: 4, total views: 4
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_one')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_two')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_three')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_four')->create();

    // Unique views: 3, total views: 5
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_one')->count(3)->create();
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_two')->create();
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_three')->create();

    expect(Post::orderByUniqueViews()->pluck('id'))->toEqual(keysOf($postThree, $postFour, $postTwo, $postOne));
});

it('can be ordered by views within a specific period in descending order', function (): void {
    $this->freezeTime();

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views within period: 3
    View::factory()->for($postOne, 'viewable')->viewedAt(Carbon::now())->create();
    View::factory()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(2))->create();
    View::factory()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(8))->create();
    View::factory()->for($postOne, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 1
    View::factory()->for($postTwo, 'viewable')->viewedAt(Carbon::now())->create();
    View::factory()->for($postTwo, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 2
    View::factory()->for($postThree, 'viewable')->viewedAt(Carbon::now())->create();
    View::factory()->for($postThree, 'viewable')->viewedAt(Carbon::now()->subDays(8))->create();
    View::factory()->for($postThree, 'viewable')->viewedAt(Carbon::now()->subDays(13))->create();

    // Views within period: 4
    View::factory()->for($postFour, 'viewable')->viewedAt(Carbon::now())->create();
    View::factory()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(3))->create();
    View::factory()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(4))->create();
    View::factory()->for($postFour, 'viewable')->viewedAt(Carbon::now()->subDays(7))->create();

    expect(Post::orderByViews('desc', Period::pastDays(10))->pluck('id'))->toEqual(keysOf($postFour, $postOne, $postThree, $postTwo));
});

it('can be ordered by views in a specific collection descending', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    View::factory()->for($postOne, 'viewable')->inCollection('wrong_collection')->count(2)->create();
    View::factory()->for($postOne, 'viewable')->create();

    // Views in collection: 2
    View::factory()->for($postTwo, 'viewable')->inCollection('good_collection')->count(2)->create();
    View::factory()->for($postTwo, 'viewable')->create();

    // Views in collection: 3
    View::factory()->for($postThree, 'viewable')->inCollection('good_collection')->count(3)->create();
    View::factory()->for($postThree, 'viewable')->inCollection('wrong_collection')->create();
    View::factory()->for($postThree, 'viewable')->create();

    // Views in collection: 1
    View::factory()->for($postFour, 'viewable')->inCollection('good_collection')->create();
    View::factory()->for($postFour, 'viewable')->create();

    expect(Post::orderByViews('desc', null, 'good_collection')->pluck('id'))->toEqual(keysOf($postThree, $postTwo, $postFour, $postOne));
});

it('can be ordered by views in a specific collection ascending', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // Views in collection: 0
    View::factory()->for($postOne, 'viewable')->inCollection('wrong_collection')->count(2)->create();
    View::factory()->for($postOne, 'viewable')->create();

    // Views in collection: 2
    View::factory()->for($postTwo, 'viewable')->inCollection('good_collection')->count(2)->create();
    View::factory()->for($postTwo, 'viewable')->create();

    // Views in collection: 3
    View::factory()->for($postThree, 'viewable')->inCollection('good_collection')->count(3)->create();
    View::factory()->for($postThree, 'viewable')->inCollection('wrong_collection')->create();
    View::factory()->for($postThree, 'viewable')->create();

    // Views in collection: 1
    View::factory()->for($postFour, 'viewable')->inCollection('good_collection')->create();
    View::factory()->for($postFour, 'viewable')->create();

    expect(Post::orderByViews('asc', null, 'good_collection')->pluck('id'))->toEqual(keysOf($postOne, $postFour, $postTwo, $postThree));
});

it('can be ordered by views in ascending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    View::factory()->for($postOne, 'viewable')->count(4)->create();

    View::factory()->for($postTwo, 'viewable')->create();

    View::factory()->for($postThree, 'viewable')->count(2)->create();

    View::factory()->for($postFour, 'viewable')->count(3)->create();

    expect(Post::orderByViews('asc')->pluck('id'))->toEqual(keysOf($postTwo, $postThree, $postFour, $postOne));
});

it('can be ordered by unique views in ascending order', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // The unique order must differ from the total order, otherwise this test
    // cannot tell orderByUniqueViews apart from orderByViews.

    // Unique views: 1, total views: 6
    View::factory()->for($postOne, 'viewable')->fromVisitor('visitor_one')->count(6)->create();

    // Unique views: 2, total views: 3
    View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
    View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->count(2)->create();

    // Unique views: 4, total views: 4
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_one')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_two')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_three')->create();
    View::factory()->for($postThree, 'viewable')->fromVisitor('visitor_four')->create();

    // Unique views: 3, total views: 5
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_one')->count(3)->create();
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_two')->create();
    View::factory()->for($postFour, 'viewable')->fromVisitor('visitor_three')->create();

    expect(Post::orderByUniqueViews('asc')->pluck('id'))->toEqual(keysOf($postOne, $postTwo, $postFour, $postThree));
});

it('can be ordered by unique views within a specific period in ascending order', function (): void {
    $this->freezeTime();

    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $postThree = Post::factory()->create();
    $postFour = Post::factory()->create();

    // The unique order within the period must differ from the total order
    // within the period, otherwise this test cannot tell unique apart.

    // Unique views within period: 3, total views within period: 6
    View::factory()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->count(4)
        ->create();
    View::factory()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(2))
        ->create();
    View::factory()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(8))
        ->create();
    View::factory()
        ->for($postOne, 'viewable')
        ->fromVisitor('visitor_four')
        ->viewedAt(Carbon::now()->subDays(13))
        ->create();

    // Unique views within period: 1, total views within period: 1
    View::factory()
        ->for($postTwo, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->create();
    View::factory()
        ->for($postTwo, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(13))
        ->count(2)
        ->create();

    // Unique views within period: 2, total views within period: 2
    View::factory()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->create();
    View::factory()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(8))
        ->create();
    View::factory()
        ->for($postThree, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(13))
        ->create();

    // Unique views within period: 4, total views within period: 4
    View::factory()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_one')
        ->viewedAt(Carbon::now())
        ->create();
    View::factory()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_two')
        ->viewedAt(Carbon::now()->subDays(3))
        ->create();
    View::factory()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_three')
        ->viewedAt(Carbon::now()->subDays(4))
        ->create();
    View::factory()
        ->for($postFour, 'viewable')
        ->fromVisitor('visitor_four')
        ->viewedAt(Carbon::now()->subDays(7))
        ->create();

    expect(Post::orderByUniqueViews('asc', Period::pastDays(10))->pluck('id'))->toEqual(keysOf($postTwo, $postThree, $postOne, $postFour));
});

it('can load the views count without loading the views', function (): void {
    View::factory()->for($this->post, 'viewable')->count(3)->create();
    View::factory()->for(Post::factory()->create(), 'viewable')->create();

    $post = Post::withViewsCount()->find($this->post->getKey());

    expect($post->views_count)->toBe(3)
        ->and($post->relationLoaded('views'))->toBeFalse();
});

it('can load the views count under a custom alias', function (): void {
    View::factory()->for($this->post, 'viewable')->count(2)->create();

    expect(Post::withViewsCount(as: 'total_views')->find($this->post->getKey())->total_views)->toBe(2);
});

it('can load the unique views count', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(3)->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

    expect(Post::withViewsCount(unique: true)->find($this->post->getKey())->views_count)->toBe(2);
});

it('can load the views count within a period and collection', function (): void {
    $this->freezeTime();

    View::factory()->for($this->post, 'viewable')->inCollection('reads')->count(2)->create();
    View::factory()->for($this->post, 'viewable')->inCollection('reads')->viewedAt(Carbon::now()->subDays(5))->create();
    View::factory()->for($this->post, 'viewable')->create();

    $post = Post::withViewsCount(Period::pastDays(2), 'reads')->find($this->post->getKey());

    expect($post->views_count)->toBe(2);
});

describe('view source', function (): void {
    beforeEach(function (): void {
        $this->app->bind(ViewSource::class, fn (): ViewSource => new class implements ViewSource
        {
            public function count(Viewable $viewable, ViewsQuery $query): int
            {
                return 0;
            }

            public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
            {
                return [];
            }

            public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
            {
                // The row's own key stands in for a count, so the ordering is observable.
                return DB::query()->selectRaw($viewable->getQualifiedKeyName());
            }
        });
    });

    it('loads the views count through the bound source', function (): void {
        View::factory()->for($this->post, 'viewable')->count(3)->create();

        expect(Post::withViewsCount()->find($this->post->getKey())->views_count)->toBe($this->post->getKey());
    });

    it('orders by views through the bound source', function (): void {
        $postTwo = Post::factory()->create();
        $postThree = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(10)->create();

        expect(Post::orderByViews()->pluck('id'))->toEqual(keysOf($postThree, $postTwo, $this->post))
            ->and(Post::orderByViews('asc')->pluck('id'))->toEqual(keysOf($this->post, $postTwo, $postThree));
    });
});
