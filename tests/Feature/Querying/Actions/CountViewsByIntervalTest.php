<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\View as ViewContract;
use CyrildeWit\EloquentViewable\Querying\Actions\CountViewsByInterval;
use CyrildeWit\EloquentViewable\Querying\Contracts\BucketGrammar;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsViewsByInterval as CountsViewsByIntervalContract;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedDriver;
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function countViewsByInterval(): CountsViewsByIntervalContract
{
    return Container::getInstance()->make(CountsViewsByIntervalContract::class);
}

function viewConnection(): Connection
{
    return Container::getInstance()->make(ViewContract::class)->getConnection();
}

function grammars(): GrammarRegistry
{
    return Container::getInstance()->make(GrammarRegistry::class);
}

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is bound to the CountViewsByInterval contract', function (): void {
    expect(countViewsByInterval())->toBeInstanceOf(CountViewsByInterval::class);
});

it('returns sparse counts keyed by the bucket start label', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-03 12:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

    expect($counts)->toBe([
        '2026-09-01 00:00:00' => 2,
        '2026-09-03 00:00:00' => 1,
    ]);
});

it('returns no counts when nothing matches', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-08-31 23:59:59'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

    expect($counts)->toBeEmpty();
});

it('counts unique visitors per bucket', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_two')->viewedAt(Carbon::parse('2026-09-01 21:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06'), unique: true), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 2]);
});

it('counts a visitor again in every bucket they return in', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 20:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-02 08:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-03 08:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06'), unique: true), Granularity::Day);

    expect($counts)->toBe([
        '2026-09-01 00:00:00' => 1,
        '2026-09-02 00:00:00' => 1,
        '2026-09-03 00:00:00' => 1,
    ]);
});

it('counts every view when the query has no period', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2024-05-04 10:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-11-05 10:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery, Granularity::Year);

    expect($counts)->toBe([
        '2024-01-01 00:00:00' => 1,
        '2026-01-01 00:00:00' => 2,
    ]);
});

it('leaves the upper bound open for a period without an end', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-08-31 23:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2030-09-01 10:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::since('2026-09-01')), Granularity::Year);

    expect($counts)->toBe([
        '2026-01-01 00:00:00' => 1,
        '2030-01-01 00:00:00' => 1,
    ]);
});

it('covers every viewable of the type when the viewable has no key', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    ViewFactory::new()->for(Post::factory()->create(), 'viewable')->viewedAt(Carbon::parse('2026-09-01 09:00:00'))->create();

    $counts = countViewsByInterval()->handle(new Post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02')), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 2]);
});

it('applies the collection', function (): void {
    ViewFactory::new()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 09:00:00'))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02'), 'custom'), Granularity::Day);

    expect($counts)->toBe(['2026-09-01 00:00:00' => 1]);
});

it('returns the same labels on every driver for {granularity}', function (Granularity $granularity, string $viewedAt, string $label): void {
    ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse($viewedAt))->create();

    $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2020-01-01', '2030-01-01')), $granularity);

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

        countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02'), unique: true), Granularity::Day);

        $sql = $connection->getQueryLog()[0]['query'];

        expect($sql)->toContain("{$expression} as interval_start")
            ->and($sql)->toContain('count(distinct '.$queryGrammar->wrap('visitor').') as aggregate')
            ->and($sql)->toContain("group by {$expression}");
    });

    it('buckets through the grammar registered for the driver of that connection', function (): void {
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-11-05 08:00:00'))->create();

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
        });

        $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-01-01', '2027-01-01')), Granularity::Day);

        expect($counts)->toBe(['2026-01-01 00:00:00' => 2]);
    });

    it('throws when no grammar is registered for the driver', function (): void {
        Container::getInstance()->instance(GrammarRegistry::class, new GrammarRegistry);

        expect(fn (): array => countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-02')), Granularity::Day))
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

        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();

        $counts = countViewsByInterval()->handle($this->post, new ViewsQuery(Period::create('2026-09-01', '2026-09-06')), Granularity::Day);

        expect($counts)->toBe(['2026-09-01 00:00:00' => 1]);
    });
});
