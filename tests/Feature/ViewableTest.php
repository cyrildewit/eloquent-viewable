<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
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

describe('views count filter', function (): void {
    beforeEach(function (): void {
        $this->popular = $this->post;
        $this->quiet = Post::factory()->create();
        $this->unseen = Post::factory()->create();

        View::factory()->for($this->popular, 'viewable')->count(3)->create();
        View::factory()->for($this->quiet, 'viewable')->create();
    });

    it('keeps the models with at least the given number of views', function (): void {
        expect(Post::whereViewsCount('>=', 3)->pluck('id'))->toEqual(keysOf($this->popular))
            ->and(Post::whereViewsCount('>', 0)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->popular, $this->quiet));
    });

    it('counts a model without views as zero', function (): void {
        expect(Post::whereViewsCount('<', 2)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->quiet, $this->unseen))
            ->and(Post::whereViewsCount('=', 0)->pluck('id'))->toEqual(keysOf($this->unseen))
            ->and(Post::whereViewsCount('!=', 0)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->popular, $this->quiet))
            ->and(Post::whereViewsCount('<>', 1)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->popular, $this->unseen))
            ->and(Post::whereViewsCount('<=', 1)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->quiet, $this->unseen));
    });

    it('only counts the views of its own type', function (): void {
        View::factory()->count(5)->create([
            'viewable_type' => (new Apartment)->getMorphClass(),
            'viewable_id' => $this->unseen->getKey(),
        ]);

        expect(Post::whereViewsCount('=', 0)->pluck('id'))->toEqual(keysOf($this->unseen));
    });

    it('keeps the models within a period and collection', function (): void {
        $this->freezeTime();

        View::factory()->for($this->unseen, 'viewable')->inCollection('reads')->count(2)->create();
        View::factory()->for($this->unseen, 'viewable')->inCollection('reads')->viewedAt(Carbon::now()->subDays(5))->create();

        expect(Post::whereViewsCount('>=', 2, Period::pastDays(2), 'reads')->pluck('id'))->toEqual(keysOf($this->unseen))
            ->and(Post::whereViewsCount('>=', 3, collection: 'reads')->pluck('id'))->toEqual(keysOf($this->unseen));
    });

    it('keeps the models by unique views', function (): void {
        View::factory()->for($this->quiet, 'viewable')->fromVisitor('visitor_one')->count(4)->create();

        expect(Post::whereViewsCount('>=', 4)->orderBy('id')->pluck('id'))->toEqual(keysOf($this->quiet))
            ->and(Post::whereUniqueViewsCount('>=', 3)->pluck('id'))->toEqual(keysOf($this->popular))
            ->and(Post::whereViewsCount('>=', 3, unique: true)->pluck('id'))->toEqual(keysOf($this->popular));
    });

    it('combines with the count and the ordering', function (): void {
        $posts = Post::whereViewsCount('>', 0)->orderByViews()->get();

        expect($posts->pluck('id'))->toEqual(keysOf($this->popular, $this->quiet))
            ->and($posts->pluck('views_count')->all())->toBe([3, 1]);
    });

    it('combines with other where clauses', function (): void {
        expect(Post::whereKey($this->quiet->getKey())->orWhere(fn ($query) => $query->whereViewsCount('=', 0))->orderBy('id')->pluck('id'))
            ->toEqual(keysOf($this->quiet, $this->unseen));
    });
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

            public function countByCollection(Viewable $viewable, ViewsQuery $query): array
            {
                return [];
            }

            public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
            {
                return [];
            }

            public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
            {
                // The row's own key stands in for a count, so the ordering is observable.
                return DB::query()->selectRaw($viewable->getQualifiedKeyName());
            }

            public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
            {
                return [];
            }
        });
    });

    it('loads the views count through the bound source', function (): void {
        View::factory()->for($this->post, 'viewable')->count(3)->create();

        expect(Post::withViewsCount()->find($this->post->getKey())->views_count)->toBe($this->post->getKey());
    });

    it('filters on views count through the bound source', function (): void {
        Post::factory()->create();

        expect(Post::whereViewsCount('=', $this->post->getKey())->pluck('id'))->toEqual(keysOf($this->post));
    });

    it('orders by views through the bound source', function (): void {
        $postTwo = Post::factory()->create();
        $postThree = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(10)->create();

        expect(Post::orderByViews()->pluck('id'))->toEqual(keysOf($postThree, $postTwo, $this->post))
            ->and(Post::orderByViews('asc')->pluck('id'))->toEqual(keysOf($this->post, $postTwo, $postThree));
    });
});

describe('viewed by', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
    });

    it('keeps the models the viewer has viewed', function (): void {
        $viewed = $this->post;
        $other = Post::factory()->create();
        Post::factory()->create();

        View::factory()->for($viewed, 'viewable')->by($this->user)->count(2)->create();
        View::factory()->for($other, 'viewable')->by(User::factory()->create())->create();
        View::factory()->for($other, 'viewable')->create();

        expect(Post::whereViewedBy($this->user)->pluck('id'))->toEqual(keysOf($viewed));
    });

    it('keeps the models the viewer has not viewed', function (): void {
        $viewed = $this->post;
        $other = Post::factory()->create();
        $unread = Post::factory()->create();

        View::factory()->for($viewed, 'viewable')->by($this->user)->create();
        View::factory()->for($other, 'viewable')->by(User::factory()->create())->create();

        expect(Post::whereNotViewedBy($this->user)->orderBy('id')->pluck('id'))->toEqual(keysOf($other, $unread));
    });

    it('narrows to a period and a collection', function (): void {
        $recent = $this->post;
        $old = Post::factory()->create();
        $sidebar = Post::factory()->create();

        View::factory()->for($recent, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-05 10:00:00'))->create();
        View::factory()->for($old, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-08-01 10:00:00'))->create();
        View::factory()->for($sidebar, 'viewable')->by($this->user)->viewedAt(Carbon::parse('2026-09-05 10:00:00'))->inCollection('sidebar')->create();

        expect(Post::whereViewedBy($this->user, Period::since('2026-09-01'))->orderBy('id')->pluck('id'))->toEqual(keysOf($recent, $sidebar))
            ->and(Post::whereViewedBy($this->user, collection: 'sidebar')->pluck('id'))->toEqual(keysOf($sidebar))
            ->and(Post::whereNotViewedBy($this->user, Period::since('2026-09-01'))->pluck('id'))->toEqual(keysOf($old));
    });

    it('accepts any model as the viewer', function (): void {
        $apartment = Apartment::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($apartment)->create();
        Post::factory()->create();

        expect(Post::whereViewedBy($apartment)->pluck('id'))->toEqual(keysOf($this->post));
    });

    it('keeps the models a visitor has or has not viewed', function (): void {
        $seen = $this->post;
        $unseen = Post::factory()->create();

        View::factory()->for($seen, 'viewable')->fromVisitor('visitor_one')->create();
        View::factory()->for($unseen, 'viewable')->fromVisitor('visitor_two')->create();

        expect(Post::whereViewedByVisitor('visitor_one')->pluck('id'))->toEqual(keysOf($seen))
            ->and(Post::whereNotViewedByVisitor('visitor_one')->pluck('id'))->toEqual(keysOf($unseen))
            ->and(Post::whereViewedByVisitor('visitor_one', Period::since('2030-01-01'))->count())->toBe(0)
            ->and(Post::whereNotViewedByVisitor('visitor_one', collection: 'sidebar')->count())->toBe(2);
    });

    it('builds an existence check on the views relation', function (): void {
        expect(Post::whereViewedBy($this->user)->toSql())
            ->toBe('select * from "posts" where exists (select * from "views" where "posts"."id" = "views"."viewable_id" and "views"."viewable_type" = ? and "views"."viewer_type" = ? and "views"."viewer_id" = ?)')
            ->and(Post::whereNotViewedByVisitor('visitor_one')->toSql())
            ->toBe('select * from "posts" where not exists (select * from "views" where "posts"."id" = "views"."viewable_id" and "views"."viewable_type" = ? and "views"."visitor" = ?)');
    })->skip(fn (): bool => driver() !== 'sqlite', 'SQL string assertions are written for the SQLite grammar');
});
