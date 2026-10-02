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
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function viewSource(): ViewSource
{
    return Container::getInstance()->make(ViewSource::class);
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

describe('count subquery', function (): void {
    /** @return array<int|string, int> */
    function countsPerPost(ViewsQuery $query): array
    {
        return Post::query()
            ->select('posts.*')
            ->selectSub(viewSource()->countSubquery(new Post, $query), 'views_count')
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
        expect(viewSource()->countSubquery(new Post, new ViewsQuery)->toSql())
            ->toBe('select count(*) from "views" where "views"."viewable_type" = ? and "views"."viewable_id" = "posts"."id"')
            ->and(viewSource()->countSubquery(new Post, new ViewsQuery(unique: true))->toSql())
            ->toBe('select count(distinct "views"."visitor") from "views" where "views"."viewable_type" = ? and "views"."viewable_id" = "posts"."id"');
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
            ->toBe(['2026-09-01 00:00:00' => 1, '2026-09-02 00:00:00' => 1]);
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
