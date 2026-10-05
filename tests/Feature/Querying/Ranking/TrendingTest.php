<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\Window;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayFactory;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Three posts, as in the README: the queues guide was busy six days ago, the
 * release notes are taking off this hour, and the Redis tips were read
 * yesterday. With a one-day half-life a view loses half its weight a day.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-04 12:30:00'));

    $this->queues = Post::factory()->create();
    $this->release = Post::factory()->create();
    $this->redis = Post::factory()->create();
    $this->quiet = Post::factory()->create();

    trendingViews($this->queues, '2026-09-28 12:10:00', 10);
    trendingViews($this->release, '2026-10-04 12:10:00', 8);
    trendingViews($this->redis, '2026-10-03 12:10:00', 4);
});

function trendingViews(Model&Viewable $viewable, string $viewedAt, int $count, ?string $visitor = null, ?string $collection = null): void
{
    for ($view = 0; $view < $count; $view++) {
        View::factory()
            ->for($viewable, 'viewable')
            ->viewedAt(Carbon::parse($viewedAt))
            ->fromVisitor($visitor ?? "visitor-{$view}")
            ->inCollection($collection)
            ->create();
    }
}

/** @return list<array{mixed, int, ?float}> */
function trendingEntries(Ranking $ranking): array
{
    return array_values($ranking->entries->map(static fn (Entry $entry): array => [$entry->viewable->getKey(), $entry->count, $entry->score])->all());
}

/** @return array<int|string, float> */
function trendingScores(): array
{
    return Post::query()->withTrendingScore()->orderBy('id')->pluck('trending_score', 'id')->all();
}

it('ranks a recent view above an older one', function (): void {
    expect(trendingEntries(views(Post::class)->trending()))->toBe([
        [$this->release->getKey(), 8, 8.0],
        [$this->redis->getKey(), 4, 2.0],
        [$this->queues->getKey(), 10, 0.15625],
    ])->and(views(Post::class)->period(Period::pastDays(7))->top()->viewables()->modelKeys())->toBe(keysOf($this->queues, $this->release, $this->redis)->all());
});

it('ranks no more than the limit', function (): void {
    expect(trendingEntries(views(Post::class)->trending(2)))->toHaveCount(2);
});

it('weighs views by the half-life of one call', function (): void {
    expect(views(Post::class)->trending(halfLife: CarbonInterval::days(2))->entries->map->score->all())->toEqualWithDelta([8.0, 2.828428, 1.25], 0.000001);
});

it('weighs views by the curve of one call', function (): void {
    expect(trendingEntries(views(Post::class)->trending(curve: new LinearDecay(CarbonInterval::days(2)))))->toBe([
        [$this->release->getKey(), 8, 8.0],
        [$this->redis->getKey(), 4, 2.0],
    ]);
});

it('forgets views past the horizon of the curve', function (): void {
    expect(views(Post::class)->trending(curve: new Window(CarbonInterval::days(3)))->viewables()->modelKeys())->toBe(keysOf($this->release, $this->redis)->all());
});

it('ranks within a period in the past, aged from its end', function (): void {
    expect(trendingEntries(views(Post::class)->period(Period::create('2026-09-28', '2026-09-29'))->trending()))->toBe([
        [$this->queues->getKey(), 10, 10 * round(0.5 ** (11 / 24) * 1_000_000) / 1_000_000],
    ]);
});

it('ranks within a collection', function (): void {
    trendingViews($this->queues, '2026-10-04 11:10:00', 1, collection: 'amp');

    expect(trendingEntries(views(Post::class)->collection('amp')->trending()))->toBe([
        [$this->queues->getKey(), 1, 0.971532],
    ]);
});

it('counts a visitor once per step', function (): void {
    trendingViews($this->quiet, '2026-10-04 12:05:00', 3, 'visitor-1');
    trendingViews($this->quiet, '2026-10-04 12:15:00', 2, 'visitor-2');
    trendingViews($this->quiet, '2026-10-04 11:20:00', 2, 'visitor-1');

    expect(trendingEntries(views(Post::class)->unique()->trending(2)))->toBe([
        [$this->release->getKey(), 8, 8.0],
        [$this->quiet->getKey(), 3, 2.971532],
    ])->and(trendingEntries(views(Post::class)->trending(2)))->toBe([
        [$this->release->getKey(), 8, 8.0],
        [$this->quiet->getKey(), 7, 6.943064],
    ]);
});

it('breaks ties by type and key', function (): void {
    $apartment = Apartment::factory()->create();
    trendingViews($apartment, '2026-10-04 12:10:00', 8);
    trendingViews($this->quiet, '2026-10-04 12:10:00', 8);

    $rows = array_map(static fn (array $row): array => [$row['type'], $row['id']], databaseSourceForTrending()->trending(null, new ViewsQuery, trendingDecay(), 3));

    expect($rows)->toBe([
        [$apartment->getMorphClass(), $apartment->getKey()],
        [$this->release->getMorphClass(), $this->release->getKey()],
        [$this->quiet->getMorphClass(), $this->quiet->getKey()],
    ]);
});

it('ranks every type through the facade', function (): void {
    $apartment = Apartment::factory()->create();
    trendingViews($apartment, '2026-10-04 11:10:00', 9);

    expect(Views::trending(2)->viewables()->map(static fn (Model $model): array => [$model::class, $model->getKey()])->all())->toBe([
        [Apartment::class, $apartment->getKey()],
        [Post::class, $this->release->getKey()],
    ]);
});

it('serializes the score', function (): void {
    expect(views(Post::class)->trending(1)->toArray()[0])->toMatchArray(['rank' => 1, 'count' => 8, 'score' => 8.0]);
});

it('refuses a limit below one', function (): void {
    views(Post::class)->trending(0);
})->throws(InvalidLimit::class, 'trending() needs a limit of at least one, 0 given.');

it('refuses to rank one viewable', function (): void {
    views($this->release)->trending();
})->throws(InvalidViewable::class);

it('refuses a half-life and a curve together', function (): void {
    views(Post::class)->trending(halfLife: CarbonInterval::day(), curve: new Window(CarbonInterval::day()));
})->throws(InvalidDecay::class);

describe('remember', function (): void {
    it('serves the remembered ranking after the clock moves on', function (): void {
        $first = trendingEntries(views(Post::class)->remember(600)->trending());

        $this->travel(30)->minutes();
        trendingViews($this->queues, '2026-10-04 12:50:00', 20);

        expect(trendingEntries(views(Post::class)->remember(600)->trending()))->toBe($first)
            ->and(trendingEntries(views(Post::class)->trending())[0][0])->toBe($this->queues->getKey());
    });

    it('keeps the rankings of two curves apart', function (): void {
        views(Post::class)->remember(600)->trending();

        expect(views(Post::class)->remember(600)->trending(curve: new Window(CarbonInterval::days(7)))->viewables()->modelKeys())
            ->toBe(keysOf($this->queues, $this->release, $this->redis)->all());
    });

    it('reads the source once', function (): void {
        views(Post::class)->remember(600)->trending();

        DB::enableQueryLog();
        views(Post::class)->remember(600)->trending();

        expect(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'views')))->toBeEmpty();
    });
});

describe('scopes', function (): void {
    it('orders by trending', function (): void {
        expect(Post::query()->orderByTrending()->pluck('id')->all())->toBe(keysOf($this->release, $this->redis, $this->queues, $this->quiet)->all())
            ->and(Post::query()->orderByTrending('asc')->pluck('id')->all())->toBe(keysOf($this->quiet, $this->queues, $this->redis, $this->release)->all());
    });

    it('selects the same scores as the ranking', function (): void {
        expect(trendingScores())->toEqual([
            $this->queues->getKey() => 0.15625,
            $this->release->getKey() => 8.0,
            $this->redis->getKey() => 2.0,
            $this->quiet->getKey() => 0.0,
        ])->and(Post::query()->withTrendingScore()->first()?->trending_score)->toBeFloat();
    });

    it('selects the same unique scores as the ranking', function (): void {
        trendingViews($this->quiet, '2026-10-04 12:05:00', 3, 'visitor-1');
        trendingViews($this->quiet, '2026-10-04 11:20:00', 2, 'visitor-1');

        $ranking = views(Post::class)->unique()->trending()->entries->mapWithKeys(static fn (Entry $entry): array => [$entry->viewable->getKey() => $entry->score])->all();

        expect(Post::query()->withTrendingScore(unique: true)->pluck('trending_score', 'id')->all())->toEqual($ranking)
            ->and(Post::query()->orderByTrending(unique: true)->pluck('id')->all())->toBe(array_keys($ranking));
    });

    it('narrows to a period and a collection', function (): void {
        trendingViews($this->quiet, '2026-10-04 12:05:00', 1, collection: 'amp');

        expect(Post::query()->withTrendingScore(Period::since('2026-10-04'), 'amp')->pluck('trending_score', 'id')->filter()->all())->toEqual([$this->quiet->getKey() => 1.0]);
    });

    it('takes a half-life, a curve and a column name', function (): void {
        expect(Post::query()->withTrendingScore(halfLife: CarbonInterval::days(2), as: 'heat')->whereKey($this->queues)->value('heat'))->toEqual(1.25)
            ->and(Post::query()->orderByTrending(curve: new Window(CarbonInterval::days(3)), as: 'heat')->whereKey($this->queues)->value('heat'))->toEqual(0.0);
    });

    it('keeps the columns already selected', function (): void {
        expect(Post::query()->select('id')->withTrendingScore()->first()?->getAttributes())->toHaveKeys(['id', 'trending_score'])->not->toHaveKey('title');
    });
});

describe('the fake', function (): void {
    it('scores the views like the database', function (): void {
        trendingViews($this->quiet, '2026-10-04 12:05:00', 3, 'visitor-1');
        trendingViews($this->quiet, '2026-10-04 11:20:00', 2, 'visitor-1');
        trendingViews($this->quiet, '2026-09-20 11:20:00', 2, 'visitor-1');

        $records = View::query()->get()->map(static fn (View $view): ViewRecord => new ViewRecord(
            $view->viewable_id,
            $view->viewable_type,
            $view->visitor,
            $view->collection,
            Carbon::parse($view->getRawOriginal('viewed_at')),
        ));

        $database = [databaseSourceForTrending()->trending(null, new ViewsQuery, trendingDecay(), 10), databaseSourceForTrending()->trending(null, new ViewsQuery(unique: true), trendingDecay(), 10)];

        $fake = Views::fake();
        $fake->storeMany($records);

        expect([$fake->trending(null, new ViewsQuery, trendingDecay(), 10), $fake->trending(new Post, new ViewsQuery(unique: true), trendingDecay(), 10)])->toEqual($database)
            ->and(trendingEntries(views(Post::class)->trending(1)))->toBe([[$this->release->getKey(), 8, 8.0]]);
    });

    it('counts the views older than every weighed step like the database', function (): void {
        $query = new ViewsQuery(Period::create('2026-09-26', '2026-10-04 12:30:00'));
        $decay = app(DecayFactory::class)->make($query, curve: new LinearDecay(CarbonInterval::days(2)));
        $database = databaseSourceForTrending()->trending(null, $query, $decay, 10);

        $fake = Views::fake();
        $fake->storeMany(View::query()->get()->map(static fn (View $view): ViewRecord => new ViewRecord(
            $view->viewable_id,
            $view->viewable_type,
            $view->visitor,
            $view->collection,
            Carbon::parse($view->getRawOriginal('viewed_at')),
        )));

        expect($fake->trending(null, $query, $decay, 10))->toEqual($database)
            ->and(array_column($database, 'count'))->toBe([8, 4, 10])
            ->and(array_column($database, 'score'))->toEqual([8.0, 2.0, 0.0]);
    });
});

it('ranks nothing over a period that has not started', function (): void {
    $period = Period::since('2026-10-05');

    expect(views(Post::class)->period($period)->trending()->isEmpty())->toBeTrue()
        ->and(views(Post::class)->period($period)->unique()->trending()->isEmpty())->toBeTrue()
        ->and(Post::query()->withTrendingScore($period)->pluck('trending_score')->unique()->values()->all())->toEqual([0.0])
        ->and(Post::query()->withTrendingScore($period, unique: true)->pluck('trending_score')->unique()->values()->all())->toEqual([0.0]);
});

describe('a source without trending', function (): void {
    beforeEach(function (): void {
        $source = Mockery::mock(ViewSource::class);
        app()->instance(ViewSource::class, $source);
    });

    it('refuses to rank', function (): void {
        views(Post::class)->trending();
    })->throws(UnsupportedBySource::class, 'cannot rank by trending, so trending() cannot read from it. Implement `CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending` on it.');

    it('refuses to rank through the cache', function (): void {
        views(Post::class)->remember(600)->trending();
    })->throws(UnsupportedBySource::class, 'cannot rank by trending');

    it('refuses the scopes', function (): void {
        Post::query()->orderByTrending();
    })->throws(UnsupportedBySource::class, 'so the withTrendingScore() and orderByTrending() scopes cannot read from it. Implement `CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource` on it');
});

function trendingDecay(?ViewsQuery $query = null): Decay
{
    return app(DecayFactory::class)->make($query ?? new ViewsQuery);
}

function databaseSourceForTrending(): DatabaseSource
{
    return app(DatabaseSource::class);
}

it('weighs per day when the config says so', function (): void {
    config()->set('eloquent-viewable.querying.trending.step', '1d');

    expect(trendingDecay()->step())->toBe(Granularity::Day)
        ->and(trendingEntries(views(Post::class)->trending()))->toBe([
            [$this->release->getKey(), 8, 8.0],
            [$this->redis->getKey(), 4, 2.0],
            [$this->queues->getKey(), 10, 0.15625],
        ]);
});
