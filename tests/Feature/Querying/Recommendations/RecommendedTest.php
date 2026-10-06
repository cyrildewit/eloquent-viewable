<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendation;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recommendations;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);
    config()->set('eloquent-viewable.querying.recommendations.similarity', 'count');

    $this->travelTo(Carbon::parse('2026-01-10 12:00:00'));

    $this->user = User::factory()->create();
    $this->read = Post::factory()->create();
    $this->earlier = Post::factory()->create();
    $this->next = Post::factory()->create();
    $this->later = Post::factory()->create();
    $this->apartment = Apartment::factory()->create();

    View::factory()->for($this->read, 'viewable')->by($this->user)->fromVisitor('own')->viewedAt(Carbon::parse('2026-01-10'))->create();
    View::factory()->for($this->earlier, 'viewable')->by($this->user)->fromVisitor('own')->viewedAt(Carbon::parse('2026-01-03'))->create();

    foreach (['one', 'two'] as $visitor) {
        View::factory()->for($this->read, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse('2026-01-09'))->create();
        View::factory()->for($this->next, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse('2026-01-09'))->create();
    }

    foreach (['three', 'four', 'five'] as $visitor) {
        View::factory()->for($this->earlier, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse('2026-01-09'))->create();
        View::factory()->for($this->later, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse('2026-01-09'))->create();
    }

    View::factory()->for($this->read, 'viewable')->fromVisitor('one')->create();
    View::factory()->for($this->apartment, 'viewable')->fromVisitor('one')->viewedAt(Carbon::parse('2026-01-09'))->create();
});

/** @return list<array{class-string, int|string, list<int|string>}> */
function recommendationsOf(Recommendations $recommendations): array
{
    return $recommendations->entries->map(fn (Recommendation $recommendation): array => [
        $recommendation->viewable::class,
        $recommendation->viewable->getKey(),
        $recommendation->because->modelKeys(),
    ])->all();
}

it('recommends what the visitors of the recent views of a viewer also viewed, the recent ones weighing more', function (): void {
    expect(recommendationsOf(views(Post::class)->viewedBy($this->user)->recommended()))->toBe([
        [Post::class, $this->next->getKey(), [$this->read->getKey()]],
        [Post::class, $this->later->getKey(), [$this->earlier->getKey()]],
    ])
        ->and(recommendationsOf(ViewsFacade::viewedBy($this->user)->recommended(2)))->toBe([
            [Post::class, $this->next->getKey(), [$this->read->getKey()]],
            [Post::class, $this->later->getKey(), [$this->earlier->getKey()]],
        ])
        ->and($this->user->recommended(Post::class, 1)->viewables()->modelKeys())->toBe([$this->next->getKey()])
        ->and($this->user->recommended()->count())->toBe(3);
});

it('scores by recency and similarity', function (): void {
    config()->set('eloquent-viewable.querying.recommendations.half_life', '7d');

    $recommendations = $this->user->recommended(Post::class);

    expect($recommendations->entries->map(fn (Recommendation $recommendation): array => [$recommendation->rank, round($recommendation->score, 4)])->all())->toBe([
        [1, round(2 * 0.5 ** (0.5 / 7), 4)],
        [2, round(3 * 0.5 ** (7.5 / 7), 4)],
    ]);
});

it('narrows the history and the pairs to a period or a collection', function (): void {
    expect($this->user->recommended(Post::class, period: Period::since('2026-01-05'))->viewables()->modelKeys())->toBe([$this->next->getKey()])
        ->and($this->user->recommended(Post::class, collection: 'sidebar')->isEmpty())->toBeTrue();
});

it('includes what the viewer viewed before when asked to', function (): void {
    View::factory()->for($this->next, 'viewable')->by($this->user)->fromVisitor('own')->viewedAt(Carbon::parse('2025-06-01'))->create();

    expect($this->user->recommended(Post::class, period: Period::since('2026-01-01'))->viewables()->modelKeys())->toBe([$this->later->getKey()])
        ->and($this->user->recommended(Post::class, period: Period::since('2026-01-01'), includeSeen: true)->viewables()->modelKeys())->toBe([$this->next->getKey(), $this->later->getKey()])
        ->and(views(Post::class)->viewedBy($this->user)->period(Period::since('2026-01-01'))->recommended(includeSeen: true)->count())->toBe(2);
});

it('recommends for the current visitor without a viewer', function (): void {
    $visitor = Mockery::mock(VisitorContract::class);
    $visitor->allows('id')->andReturn('one');

    expect(recommendationsOf(views(Post::class)->useVisitor($visitor)->recommended()))->toBe([
        [Post::class, $this->earlier->getKey(), [$this->read->getKey()]],
    ]);
});

it('keeps the recommended models of a type in a query, ordered by score, with the score selected', function (): void {
    $posts = Post::query()->recommendedFor($this->user)->get();

    expect($posts->modelKeys())->toBe([$this->next->getKey(), $this->later->getKey()])
        ->and($posts->first()->recommendation_score)->toBeFloat()->toBeGreaterThan($posts->last()->recommendation_score)
        ->and(Post::query()->whereKeyNot($this->next->getKey())->recommendedFor($this->user)->paginate(1)->items()[0]->getKey())->toBe($this->later->getKey())
        ->and(Post::query()->select('id')->recommendedFor($this->user, as: 'score')->first()?->getAttributes())->toHaveKeys(['id', 'score'])
        ->and(Post::query()->recommendedFor('one')->pluck('id')->all())->toBe([$this->earlier->getKey()])
        ->and(Post::query()->recommendedFor($this->user, Period::since('2026-01-05'))->pluck('id')->all())->toBe([$this->next->getKey()])
        ->and(Post::query()->recommendedFor($this->user, collection: 'sidebar')->count())->toBe(0)
        ->and(Apartment::query()->recommendedFor($this->user)->pluck('id')->all())->toBe([$this->apartment->getKey()]);
});

it('includes what the viewer viewed before in a query when asked to', function (): void {
    View::factory()->for($this->next, 'viewable')->by($this->user)->fromVisitor('own')->viewedAt(Carbon::parse('2025-06-01'))->create();

    expect(Post::query()->recommendedFor($this->user, Period::since('2026-01-01'))->pluck('id')->all())->toBe([$this->later->getKey()])
        ->and(Post::query()->recommendedFor($this->user, Period::since('2026-01-01'), includeSeen: true)->pluck('id')->all())->toBe([$this->next->getKey(), $this->later->getKey()]);
});

it('leaves out a recommended model that no longer exists, and a reason that no longer exists', function (): void {
    $this->next->delete();
    View::factory()->for($this->next, 'viewable')->fromVisitor('one')->create();
    $this->read->deleteQuietly();

    expect(recommendationsOf($this->user->recommended(Post::class)))->toBe([
        [Post::class, $this->later->getKey(), [$this->earlier->getKey()]],
    ]);
});

it('can remember the recommendations until the cache is flushed', function (): void {
    expect(views(Post::class)->viewedBy($this->user)->remember(60)->recommended()->count())->toBe(2);

    View::factory()->for(Post::factory()->create(), 'viewable')->fromVisitor('one')->create();

    expect(views(Post::class)->viewedBy($this->user)->remember(60)->recommended()->count())->toBe(2)
        ->and(views(Post::class)->viewedBy($this->user)->recommended()->count())->toBe(3);

    views(Post::class)->flushCache();

    expect(views(Post::class)->viewedBy($this->user)->remember(60)->recommended()->count())->toBe(3);
});

it('refuses a limit below one', function (): void {
    expect(fn (): Recommendations => $this->user->recommended(limit: 0))
        ->toThrow(InvalidLimit::class, 'recommended() needs a limit of at least one, 0 given.');
});

it('refuses to recommend among one saved model', function (): void {
    expect(fn (): Recommendations => views($this->read)->viewedBy($this->user)->recommended())
        ->toThrow(InvalidViewable::class, 'recommended() ranks among every viewable of a type or every type.');
});

it('refuses to recommend among a class that is not viewable', function (): void {
    expect(fn (): Recommendations => $this->user->recommended(User::class))
        ->toThrow(InvalidViewable::class, 'Class ['.User::class.'] must implement');
});

it('refuses a source that cannot recommend', function (bool $remember): void {
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

        public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
        {
            return [];
        }
    });

    views(Post::class)->viewedBy($this->user)->remember($remember ? 60 : null)->recommended();
})->with(['read' => false, 'remembered' => true])->throws(UnsupportedBySource::class, 'cannot read what recommendations are made from, so recommended() and recommendedFor() cannot read from it.');

it('recommends through the rollup source', function (): void {
    config()->set('eloquent-viewable.querying.source.driver', 'rollup');

    expect($this->user->recommended(Post::class)->viewables()->modelKeys())->toBe([$this->next->getKey(), $this->later->getKey()]);
});

it('keeps a model the viewer viewed out of the cosine ranking of a popular one', function (): void {
    config()->set('eloquent-viewable.querying.recommendations.similarity', 'cosine');
    $popular = Post::factory()->create();

    foreach (['one', 'two', 'a', 'b', 'c', 'd', 'e', 'f'] as $visitor) {
        View::factory()->for($popular, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse('2026-01-09'))->create();
    }

    expect(views(Post::class)->viewedBy($this->user)->recommended(1)->viewables()->modelKeys())->toBe([$this->next->getKey()])
        ->and(collect(views(Post::class)->viewedBy($this->user)->recommended()->viewables()->modelKeys()))->toContain($popular->getKey());

    config()->set('eloquent-viewable.querying.recommendations.max_seeds', 1);

    expect(views(Post::class)->viewedBy($this->user)->recommended()->viewables()->modelKeys())->not->toContain($this->later->getKey());
});
