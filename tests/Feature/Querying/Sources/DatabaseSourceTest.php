<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedDriver;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Querying\Pairs\PairTable;
use CyrildeWit\EloquentViewable\Querying\Sources\CoVisitation;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\KeepsViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function viewSource(): ViewSource
{
    return Container::getInstance()->make(ViewSource::class);
}

function databaseSource(): DatabaseSource
{
    return Container::getInstance()->make(DatabaseSource::class);
}

function viewConnection(): Connection
{
    return Container::getInstance()->make(View::class)->getConnection();
}

function grammars(): GrammarRegistry
{
    return Container::getInstance()->make(GrammarRegistry::class);
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is the default ViewSource', function (): void {
    expect(viewSource())->toBeInstanceOf(DatabaseSource::class);
});

describe('count', function (): void {
    it('counts the views of a viewable', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(viewSource()->count($this->post, new ViewsQuery))->toBe(2);
    });

    it('counts the views of a viewable type', function (): void {
        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(viewSource()->count(new Post, new ViewsQuery))->toBe(2);
    });

    it('counts unique visitors', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

        expect(viewSource()->count($this->post, new ViewsQuery(unique: true)))->toBe(2);
    });

    it('applies the period and collection', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-01-10'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-02-10'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();

        expect(viewSource()->count($this->post, new ViewsQuery(Period::since('2026-02-01'), 'custom')))->toBe(1);
    });
});

describe('count many', function (): void {
    it('counts the views of each key, zero for a key without views', function (): void {
        $other = Post::factory()->create();
        $unviewed = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(3)->create();
        View::factory()->for($other, 'viewable')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(viewSource()->countMany(new Post, [$this->post->getKey(), $other->getKey(), $unviewed->getKey()], new ViewsQuery))
            ->toEqual([$this->post->getKey() => 3, $other->getKey() => 1, $unviewed->getKey() => 0]);
    });

    it('only counts views of the type', function (): void {
        $apartment = Apartment::factory()->create();

        View::factory()->for($apartment, 'viewable')->count(2)->create();

        expect(viewSource()->countMany(new Post, [$apartment->getKey()], new ViewsQuery))->toBe([$apartment->getKey() => 0]);
    });

    it('counts unique visitors per key', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(3)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

        expect(viewSource()->countMany(new Post, [$this->post->getKey()], new ViewsQuery(unique: true)))->toBe([$this->post->getKey() => 2]);
    });

    it('applies the period, collection and viewer per key', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-01-10'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-02-10'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->by($user)->viewedAt(Carbon::parse('2026-02-11'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();

        expect(viewSource()->countMany(new Post, [$this->post->getKey()], new ViewsQuery(Period::since('2026-02-01'), 'custom')))->toBe([$this->post->getKey() => 2])
            ->and(viewSource()->countMany(new Post, [$this->post->getKey()], new ViewsQuery(Period::since('2026-02-01'), 'custom', viewer: $user)))->toBe([$this->post->getKey() => 1]);
    });

    it('inlines integer keys and binds any other', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        $connection = viewConnection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $integers = viewSource()->countMany(new Post, [$this->post->getKey()], new ViewsQuery);
        $strings = viewSource()->countMany(new Post, [(string) $this->post->getKey(), '999999999'], new ViewsQuery);

        [$inlined, $bound] = $connection->getQueryLog();
        $connection->disableQueryLog();

        expect($integers)->toBe([$this->post->getKey() => 2])
            ->and($strings)->toEqual([$this->post->getKey() => 2, 999999999 => 0])
            ->and($inlined['bindings'])->toBe([$this->post->getMorphClass()])
            ->and($bound['bindings'])->toBe([(string) $this->post->getKey(), $this->post->getMorphClass(), (string) $this->post->getKey(), '999999999', $this->post->getMorphClass(), '999999999']);
    });

    it('splits a long list of keys into one query per hundred', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        $first = $this->post->getKey() + 1;
        $keys = [...range($first, $first + 149), $this->post->getKey()];

        $connection = viewConnection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $counts = viewSource()->countMany(new Post, $keys, new ViewsQuery);
        $queries = count($connection->getQueryLog());
        $connection->disableQueryLog();

        expect($counts)->toHaveCount(151)
            ->and($counts[$this->post->getKey()])->toBe(2)
            ->and(array_sum($counts))->toBe(2)
            ->and($queries)->toBe(2);
    });
});

describe('count subquery', function (): void {
    /** @return array<int|string, int> */
    function countsPerPost(ViewsQuery $query): array
    {
        return Post::query()
            ->select('posts.*')
            ->selectSub(databaseSource()->countSubquery(new Post, $query), 'views_count')
            ->orderBy('id')
            ->pluck('views_count', 'id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    it('counts the views of each row of the outer query', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(3)->create();
        View::factory()->for($other, 'viewable')->create();
        View::factory()->for(Apartment::factory()->create(), 'viewable')->count(5)->create();

        expect(countsPerPost(new ViewsQuery))->toBe([
            $this->post->getKey() => 3,
            $other->getKey() => 1,
        ]);
    });

    it('counts unique visitors per row', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(3)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

        expect(countsPerPost(new ViewsQuery(unique: true)))->toBe([$this->post->getKey() => 2]);
    });

    it('applies the period and collection per row', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-01-10'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-02-10'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();

        expect(countsPerPost(new ViewsQuery(Period::since('2026-02-01'), 'custom')))->toBe([$this->post->getKey() => 1]);
    });

    it('selects a single aggregate column', function (): void {
        expect(databaseSource()->countSubquery(new Post, new ViewsQuery)->toSql())
            ->toBe('select count(*) from "views" where "views"."viewable_type" = ? and "views"."viewable_id" = "posts"."id"')
            ->and(databaseSource()->countSubquery(new Post, new ViewsQuery(unique: true))->toSql())
            ->toBe('select count(distinct "views"."visitor") from "views" where "views"."viewable_type" = ? and "views"."viewable_id" = "posts"."id"');
    })->skip(fn (): bool => driver() !== 'sqlite', 'SQL string assertions are written for the SQLite grammar');
});

describe('views subquery', function (): void {
    it('narrows to the visitor when one is given', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->create();
        View::factory()->for($other, 'viewable')->fromVisitor('visitor_two')->create();

        $viewed = fn (?string $visitor): array => Post::query()
            ->whereExists(databaseSource()->viewsSubquery(new Post, new ViewsQuery, $visitor))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        expect($viewed('visitor_one'))->toBe([$this->post->getKey()])
            ->and($viewed(null))->toBe([$this->post->getKey(), $other->getKey()]);
    });
});

describe('cache identity', function (): void {
    it('names the connection and the database the views are read from', function (): void {
        Config::set('database.connections.analytics', ['driver' => 'sqlite', 'database' => ':memory:']);

        $analytics = new View;
        $analytics->setConnection('analytics');

        expect(databaseSource()->cacheIdentity())->toBe(json_encode([viewConnection()->getName(), viewConnection()->getDatabaseName()]))
            ->and(new DatabaseSource($analytics, grammars(), new CoVisitation($analytics), Container::getInstance()->make(PairTable::class))->cacheIdentity())->toBe('["analytics",":memory:"]');
    });
});

describe('top', function (): void {
    it('ranks the viewables of every type by their views', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for($apartment, 'viewable')->count(3)->create();
        View::factory()->for($other, 'viewable')->create();

        expect(viewSource()->top(null, new ViewsQuery, 10))->toBe([
            ['type' => $apartment->getMorphClass(), 'id' => $apartment->getKey(), 'count' => 3],
            ['type' => $this->post->getMorphClass(), 'id' => $this->post->getKey(), 'count' => 2],
            ['type' => $other->getMorphClass(), 'id' => $other->getKey(), 'count' => 1],
        ]);
    });

    it('ranks within one type when the viewable has no key', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for($other, 'viewable')->count(2)->create();
        View::factory()->for(Apartment::factory()->create(), 'viewable')->count(3)->create();

        expect(array_column(viewSource()->top(new Post, new ViewsQuery, 10), 'id'))->toBe([$other->getKey(), $this->post->getKey()]);
    });

    it('stops at the limit', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for($other, 'viewable')->create();

        expect(viewSource()->top(null, new ViewsQuery, 1))->toBe([
            ['type' => $this->post->getMorphClass(), 'id' => $this->post->getKey(), 'count' => 2],
        ]);
    });

    it('breaks ties on the type, then the key', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        View::factory()->for($other, 'viewable')->create();
        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for($apartment, 'viewable')->create();

        expect(array_map(fn (array $row): array => [$row['type'], $row['id']], viewSource()->top(null, new ViewsQuery, 10)))->toBe([
            [$apartment->getMorphClass(), $apartment->getKey()],
            [$this->post->getMorphClass(), $this->post->getKey()],
            [$other->getMorphClass(), $other->getKey()],
        ]);
    });

    it('counts unique visitors', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->fromVisitor('one')->count(3)->create();
        View::factory()->for($other, 'viewable')->fromVisitor('one')->create();
        View::factory()->for($other, 'viewable')->fromVisitor('two')->create();

        expect(array_column(viewSource()->top(null, new ViewsQuery(unique: true), 10), 'count'))->toBe([2, 1])
            ->and(array_column(viewSource()->top(null, new ViewsQuery, 10), 'count'))->toBe([3, 2]);
    });

    it('applies the period, collection and viewer', function (): void {
        $other = Post::factory()->create();
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-01-10'))->count(3)->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();
        View::factory()->for($other, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-02-10'))->by($user)->count(2)->create();

        expect(array_column(viewSource()->top(null, new ViewsQuery(Period::since('2026-02-01')), 10), 'id'))->toBe([$other->getKey(), $this->post->getKey()])
            ->and(array_column(viewSource()->top(null, new ViewsQuery(collection: 'custom'), 10), 'id'))->toBe([$this->post->getKey(), $other->getKey()])
            ->and(array_column(viewSource()->top(null, new ViewsQuery(Period::since('2026-02-01'), 'custom'), 10), 'id'))->toBe([$other->getKey()])
            ->and(array_column(viewSource()->top(null, new ViewsQuery(viewer: $user), 10), 'id'))->toBe([$other->getKey()]);
    });

    it('returns nothing when no view matches', function (): void {
        expect(viewSource()->top(null, new ViewsQuery, 10))->toBeEmpty();
    });

    it('keeps the rows of a viewable whose model is gone', function (): void {
        $post = KeepsViewsPost::create(['title' => 'Title', 'body' => 'Body']);
        View::factory()->for($post, 'viewable')->count(2)->create();
        $post->delete();

        expect(viewSource()->top(null, new ViewsQuery, 10))->toBe([
            ['type' => $post->getMorphClass(), 'id' => $post->getKey(), 'count' => 2],
        ]);
    });

    it('groups and orders in one statement', function (): void {
        DB::enableQueryLog();

        viewSource()->top(new Post, new ViewsQuery(Period::create('2026-01-01', '2026-02-01'), 'custom', unique: true), 5);

        expect(array_column(DB::getQueryLog(), 'query'))->toBe([
            'select "views"."viewable_type", "views"."viewable_id", count(distinct "views"."visitor") as aggregate from "views" where "viewed_at" >= ? and "viewed_at" < ? and "collection" = ? and "views"."viewable_type" = ? group by "views"."viewable_type", "views"."viewable_id" order by "aggregate" desc, "views"."viewable_type" asc, "views"."viewable_id" asc limit 5',
        ]);
    })->skip(fn (): bool => driver() !== 'sqlite', 'SQL string assertions are written for the SQLite grammar');
});

describe('also viewed', function (): void {
    /** @param  list<string>  $visitors */
    function seenByVisitors(Model $viewable, array $visitors, string $viewedAt = '2026-01-10'): void
    {
        foreach ($visitors as $visitor) {
            View::factory()->for($viewable, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse($viewedAt))->create();
        }
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    function alsoViewedOf(Post $post, ViewsQuery $query = new ViewsQuery, ?Model $among = null, int $limit = 10, int $minimum = 1, ?int $maxVisitors = null): array
    {
        return databaseSource()->alsoViewed($post, $among, $query, $limit, $minimum, $maxVisitors);
    }

    it('ranks what the visitors of the viewable also viewed by distinct visitors', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        seenByVisitors($this->post, ['one', 'two', 'three']);
        seenByVisitors($apartment, ['one', 'two', 'two', 'two']);
        seenByVisitors($other, ['three', 'stranger', 'stranger']);

        expect(alsoViewedOf($this->post))->toBe([
            ['type' => $apartment->getMorphClass(), 'id' => $apartment->getKey(), 'count' => 2],
            ['type' => $other->getMorphClass(), 'id' => $other->getKey(), 'count' => 1],
        ]);
    });

    it('never ranks the viewable itself', function (): void {
        seenByVisitors($this->post, ['one', 'one', 'two']);

        expect(alsoViewedOf($this->post))->toBeEmpty();
    });

    it('ranks a viewable of another type that shares the key', function (): void {
        $apartment = Apartment::factory()->create(['id' => $this->post->getKey()]);

        seenByVisitors($this->post, ['one']);
        seenByVisitors($apartment, ['one']);

        expect(alsoViewedOf($this->post))->toBe([
            ['type' => $apartment->getMorphClass(), 'id' => $this->post->getKey(), 'count' => 1],
        ]);
    });

    it('never pairs views without a visitor', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->state(['visitor' => null])->create();
        View::factory()->for($other, 'viewable')->state(['visitor' => null])->create();

        expect(alsoViewedOf($this->post))->toBeEmpty();
    });

    it('leaves out what fewer visitors than the minimum viewed', function (): void {
        $popular = Post::factory()->create();
        $rare = Post::factory()->create();

        seenByVisitors($this->post, ['one', 'two']);
        seenByVisitors($popular, ['one', 'two']);
        seenByVisitors($rare, ['one']);

        expect(array_column(alsoViewedOf($this->post, minimum: 2), 'id'))->toBe([$popular->getKey()]);
    });

    it('ranks among one type', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        seenByVisitors($this->post, ['one', 'two']);
        seenByVisitors($apartment, ['one', 'two']);
        seenByVisitors($other, ['one']);

        expect(array_column(alsoViewedOf($this->post, among: new Post), 'id'))->toBe([$other->getKey()]);
    });

    it('stops at the limit and breaks ties on the type, then the key', function (): void {
        $apartment = Apartment::factory()->create();
        $first = Post::factory()->create();
        $second = Post::factory()->create();

        seenByVisitors($this->post, ['one']);
        seenByVisitors($second, ['one']);
        seenByVisitors($first, ['one']);
        seenByVisitors($apartment, ['one']);

        expect(array_map(fn (array $row): array => [$row['type'], $row['id']], alsoViewedOf($this->post)))->toBe([
            [$apartment->getMorphClass(), $apartment->getKey()],
            [$first->getMorphClass(), $first->getKey()],
            [$second->getMorphClass(), $second->getKey()],
        ])
            ->and(array_column(alsoViewedOf($this->post, limit: 1), 'id'))->toBe([$apartment->getKey()]);
    });

    it('reads only the most recent visitors of the viewable', function (): void {
        $old = Post::factory()->create();
        $recent = Post::factory()->create();

        seenByVisitors($this->post, ['early'], '2026-01-01');
        seenByVisitors($this->post, ['late'], '2026-01-20');
        seenByVisitors($old, ['early']);
        seenByVisitors($recent, ['late']);

        expect(array_column(alsoViewedOf($this->post, maxVisitors: 1), 'id'))->toBe([$recent->getKey()])
            ->and(array_column(alsoViewedOf($this->post, maxVisitors: 2), 'id'))->toBe([$old->getKey(), $recent->getKey()]);
    });

    it('applies the period and collection to both sides of the pair', function (): void {
        $other = Post::factory()->create();

        seenByVisitors($this->post, ['january'], '2026-01-10');
        seenByVisitors($this->post, ['february'], '2026-02-10');
        seenByVisitors($other, ['january', 'february'], '2026-02-10');
        View::factory()->for($this->post, 'viewable')->fromVisitor('sidebar')->inCollection('sidebar')->viewedAt(Carbon::parse('2026-01-10'))->create();
        View::factory()->for($other, 'viewable')->fromVisitor('sidebar')->inCollection('sidebar')->viewedAt(Carbon::parse('2026-01-10'))->create();

        expect(alsoViewedOf($this->post)[0]['count'])->toBe(3)
            ->and(alsoViewedOf($this->post, new ViewsQuery(Period::since('2026-02-01')))[0]['count'])->toBe(1)
            ->and(alsoViewedOf($this->post, new ViewsQuery(collection: 'sidebar'))[0]['count'])->toBe(1);
    });

    it('returns nothing when the viewable has no views', function (): void {
        seenByVisitors(Post::factory()->create(), ['one']);

        expect(alsoViewedOf($this->post))->toBeEmpty();
    });

    it('pairs through the visitors of the viewable in one statement', function (): void {
        DB::enableQueryLog();

        alsoViewedOf($this->post, new ViewsQuery(Period::create('2026-01-01', '2026-02-01')), new Post, 5, 3, 100);

        expect(array_column(DB::getQueryLog(), 'query'))->toBe([
            'select "views"."viewable_type", "views"."viewable_id", count(distinct "views"."visitor") as aggregate from "views" inner join (select "views"."visitor" as "anchor_visitor" from "views" where "viewable_type" = ? and "viewable_id" = ? and "viewed_at" >= ? and "viewed_at" < ? and "views"."visitor" is not null group by "views"."visitor" order by max("views"."viewed_at") desc, "views"."visitor" asc limit 100) as "anchor" on "anchor"."anchor_visitor" = "views"."visitor" where "viewed_at" >= ? and "viewed_at" < ? and ("views"."viewable_type" != ? or "views"."viewable_id" != ?) and "views"."viewable_type" = ? group by "views"."viewable_type", "views"."viewable_id" having count(distinct "views"."visitor") >= ? order by "aggregate" desc, "views"."viewable_type" asc, "views"."viewable_id" asc limit 5',
        ]);
    })->skip(fn (): bool => driver() !== 'sqlite', 'SQL string assertions are written for the SQLite grammar');
});

it('returns sparse counts keyed by the bucket start label', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-03 12:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

    expect($counts)->toBe([
        '2026-09-01 00:00:00' => 2,
        '2026-09-03 00:00:00' => 1,
    ]);
});

it('returns no counts when nothing matches', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-08-31 23:59:59'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

    expect($counts)->toBeEmpty();
});

it('counts unique visitors per bucket', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->viewedAt(Carbon::parse('2026-09-01 21:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06'), unique: true), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 2]);
});

it('counts a visitor again in every bucket they return in', function (): void {
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-02 08:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-03 08:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06'), unique: true), Granularity::Day);

    expect($counts)->toBe([
        '2026-09-01 00:00:00' => 1,
        '2026-09-02 00:00:00' => 1,
        '2026-09-03 00:00:00' => 1,
    ]);
});

it('counts every view when the query has no period', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2024-05-04 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-11-05 10:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery, Granularity::Year);

    expect($counts)->toBe([
        '2024-01-01 00:00:00' => 1,
        '2026-01-01 00:00:00' => 2,
    ]);
});

it('leaves the upper bound open for a period without an end', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-08-31 23:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2030-09-01 10:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::since('2026-09-01')), Granularity::Year);

    expect($counts)->toBe([
        '2026-01-01 00:00:00' => 1,
        '2030-01-01 00:00:00' => 1,
    ]);
});

it('covers every viewable of the type when the viewable has no key', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    View::factory()->for(Post::factory()->create(), 'viewable')->viewedAt(Carbon::parse('2026-09-01 09:00:00'))->create();

    $counts = viewSource()->countByInterval(new Post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02')), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 2]);
});

it('applies the collection', function (): void {
    View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 09:00:00'))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02'), 'custom'), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 1]);
});

it('returns the same labels on every driver for {granularity}', function (Granularity $granularity, string $viewedAt, string $label): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();

    $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2020-01-01', '2030-01-01')), $granularity);

    expect($counts)->toBe([$label => 1]);
})->with([
    'hour' => [Granularity::Hour, '2026-03-04 10:37:12', '2026-03-04 10:00:00'],
    'day' => [Granularity::Day, '2026-03-04 10:37:12', '2026-03-04 00:00:00'],
    'week, a wednesday' => [Granularity::Week, '2026-03-04 10:37:12', '2026-03-02 00:00:00'],
    'week, a sunday' => [Granularity::Week, '2026-03-08 23:59:59', '2026-03-02 00:00:00'],
    'week, a monday' => [Granularity::Week, '2026-03-02 00:00:00', '2026-03-02 00:00:00'],
    'month' => [Granularity::Month, '2026-03-04 10:37:12', '2026-03-01 00:00:00'],
    'year' => [Granularity::Year, '2026-03-04 10:37:12', '2026-01-01 00:00:00'],
]);

describe('visit frequency', function (): void {
    /** @param  list<array{string|null, string}>  $views */
    function visitedOn(Model $viewable, array $views): void
    {
        foreach ($views as [$visitor, $viewedAt]) {
            View::factory()->for($viewable, 'viewable')->state(['visitor' => $visitor])->viewedAt(Carbon::parse($viewedAt))->create();
        }
    }

    /** @return array<int, int> */
    function frequencyOf(Model $viewable, ViewsQuery $query = new ViewsQuery): array
    {
        $counts = databaseSource()->visitFrequency($viewable, $query);

        ksort($counts);

        return $counts;
    }

    it('counts the visitors per number of days they viewed on', function (): void {
        visitedOn($this->post, [
            ['one', '2026-01-10 09:00:00'],
            ['one', '2026-01-10 18:00:00'],
            ['one', '2026-01-12 09:00:00'],
            ['one', '2026-01-15 09:00:00'],
            ['two', '2026-01-10 09:00:00'],
            ['two', '2026-01-11 09:00:00'],
            ['three', '2026-01-10 09:00:00'],
            ['three', '2026-01-10 09:05:00'],
            ['four', '2026-01-11 09:00:00'],
        ]);

        expect(frequencyOf($this->post))->toBe([1 => 2, 2 => 1, 3 => 1]);
    });

    it('is empty without views', function (): void {
        expect(frequencyOf($this->post))->toBeEmpty();
    });

    it('leaves out views without a visitor and anonymised views', function (): void {
        visitedOn($this->post, [
            [null, '2026-01-10 09:00:00'],
            [null, '2026-01-11 09:00:00'],
            ['a:first-day', '2026-01-10 09:00:00'],
            ['a:first-day', '2026-01-10 10:00:00'],
            ['one', '2026-01-10 09:00:00'],
        ]);

        expect(frequencyOf($this->post))->toBe([1 => 1]);
    });

    it('reads only the views of the viewable', function (): void {
        visitedOn($this->post, [['one', '2026-01-10 09:00:00']]);
        visitedOn(Post::factory()->create(), [['one', '2026-01-11 09:00:00']]);

        expect(frequencyOf($this->post))->toBe([1 => 1])
            ->and(frequencyOf(new Post))->toBe([2 => 1]);
    });

    it('reads only the days inside the period, the collection and the viewer', function (): void {
        $user = User::factory()->create();

        visitedOn($this->post, [
            ['one', '2026-01-10 09:00:00'],
            ['one', '2026-01-20 09:00:00'],
        ]);
        View::factory()->for($this->post, 'viewable')->fromVisitor('one')->inCollection('sidebar')->viewedAt(Carbon::parse('2026-01-21 09:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('two')->by($user)->viewedAt(Carbon::parse('2026-01-21 09:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('two')->by($user)->viewedAt(Carbon::parse('2026-01-22 09:00:00'))->create();

        expect(frequencyOf($this->post, new ViewsQuery(Period::since('2026-01-15'))))->toBe([2 => 2])
            ->and(frequencyOf($this->post, new ViewsQuery(collection: 'sidebar')))->toBe([1 => 1])
            ->and(frequencyOf($this->post, new ViewsQuery(viewer: $user)))->toBe([2 => 1]);
    });

    it('counts the days on the clock of the query timezone', function (): void {
        visitedOn($this->post, [
            ['one', '2026-01-10 23:30:00'],
            ['one', '2026-01-11 00:30:00'],
        ]);

        $period = Period::create('2026-01-01', '2026-02-01');

        expect(frequencyOf($this->post, new ViewsQuery($period)))->toBe([2 => 1])
            ->and(frequencyOf($this->post, new ViewsQuery($period, timezone: new Timezone('Australia/Sydney'))))->toBe([1 => 1]);
    });

    it('requires a period to count days in another timezone', function (): void {
        expect(fn (): array => databaseSource()->visitFrequency($this->post, new ViewsQuery(timezone: new Timezone('Australia/Sydney'))))
            ->toThrow(InvalidInterval::class);
    });
});

describe('count by collection', function (): void {
    it('counts the views of a viewable per collection, the default one as an empty string', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->count(3)->create();
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->inCollection('feed')->create();

        $counts = viewSource()->countByCollection($this->post, new ViewsQuery);

        ksort($counts);

        expect($counts)->toBe(['' => 2, 'feed' => 1, 'sidebar' => 3]);
    });

    it('counts the views of a viewable type per collection', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->inCollection('sidebar')->create();
        View::factory()->for(Apartment::factory()->create(), 'viewable')->inCollection('sidebar')->create();

        expect(viewSource()->countByCollection(new Post, new ViewsQuery))->toBe(['sidebar' => 2]);
    });

    it('returns no counts when nothing matches', function (): void {
        expect(viewSource()->countByCollection($this->post, new ViewsQuery))->toBeEmpty();
    });

    it('counts unique visitors per collection', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->inCollection('sidebar')->count(2)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->inCollection('sidebar')->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->create();

        $counts = viewSource()->countByCollection($this->post, new ViewsQuery(unique: true));

        ksort($counts);

        expect($counts)->toBe(['' => 1, 'sidebar' => 2]);
    });

    it('narrows to the period of the query', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->viewedAt(Carbon::parse('2026-08-31 23:59:59'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->viewedAt(Carbon::parse('2026-09-01 00:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->viewedAt(Carbon::parse('2026-09-06 00:00:00'))->create();

        expect(viewSource()->countByCollection($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06'))))->toBe(['sidebar' => 1]);
    });

    it('narrows to the collection of the query', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->create();
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->create();

        expect(viewSource()->countByCollection($this->post, new ViewsQuery(collection: 'sidebar')))->toBe(['sidebar' => 1]);
    });
});

describe('grammar resolution', function (): void {
    it('wraps both columns through the grammar of the connection it queries', function (): void {
        $connection = viewConnection();
        $queryGrammar = $connection->getQueryGrammar();

        $expression = grammars()
            ->for($connection->getDriverName())
            ->truncate($queryGrammar->wrap('viewed_at'), Granularity::Day);

        $connection->enableQueryLog();

        viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02'), unique: true), Granularity::Day);

        $sql = $connection->getQueryLog()[0]['query'];

        expect($sql)->toContain("{$expression} as interval_start")
            ->and($sql)->toContain('count(distinct '.$queryGrammar->wrap('views.visitor').') as aggregate')
            ->and($sql)->toContain("group by {$queryGrammar->wrap('interval_start')}");
    });

    it('buckets through the grammar registered for the driver of that connection', function (): void {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-11-05 08:00:00'))->create();

        $driver = viewConnection()->getDriverName();

        // Delegates to the real grammar so the SQL stays valid on every driver,
        // but ignores the granularity it is handed.
        grammars()->register($driver, new readonly class(grammars()->for($driver)) implements BucketGrammar
        {
            public function __construct(private BucketGrammar $grammar) {}

            public function truncate(string $column, Granularity $granularity): string
            {
                return $this->grammar->truncate($column, Granularity::Year);
            }

            public function convertTimezone(string $column, TimezoneConversion $conversion): string
            {
                return $this->grammar->convertTimezone($column, $conversion);
            }
        });

        $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-01-01', '2027-01-01')), Granularity::Day);

        expect($counts)->toBe(['2026-01-01 00:00:00' => 2]);
    });

    it('converts the stored wall clock to the query timezone before bucketing', function (): void {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 13:00:00', 'UTC'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

        $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-03 00:00:00', 'UTC'));

        expect(viewSource()->countByInterval($this->post, new ViewsQuery($period, timezone: new Timezone('Australia/Sydney')), Granularity::Day))
            ->toEqual(['2026-09-01 00:00:00' => 1, '2026-09-02 00:00:00' => 1]);
    });

    it('requires a period to convert timezones', function (): void {
        expect(fn (): array => viewSource()->countByInterval($this->post, new ViewsQuery(timezone: new Timezone('Australia/Sydney')), Granularity::Day))
            ->toThrow(InvalidInterval::class, 'requires a period with a start date time');
    });

    it('throws when no grammar is registered for the driver', function (): void {
        Container::getInstance()->instance(GrammarRegistry::class, new GrammarRegistry);

        expect(fn (): array => viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02')), Granularity::Day))
            ->toThrow(UnsupportedDriver::class, '`'.viewConnection()->getDriverName().'`');
    });

    it('reads from the connection configured for the view model', function (): void {
        Config::set('database.connections.views_store', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::connection('views_store')->create('views', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->morphs('viewable');
            $table->string('visitor')->nullable();
            $table->string('collection')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
        });

        // A row on the default connection that must not reach the counts.
        DB::table('views')->insert([
            'viewable_id' => $this->post->getKey(),
            'viewable_type' => $this->post->getMorphClass(),
            'visitor' => 'unique_hash',
            'collection' => null,
            'viewed_at' => '2026-09-02 08:00:00',
        ]);

        Config::set('eloquent-viewable.models.view.connection', 'views_store');

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();

        $counts = viewSource()->countByInterval($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

        expect($counts)->toBe(['2026-09-01 00:00:00' => 1]);
    });
});
