<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Reader;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;

function readerViewable(): Viewable
{
    $connection = Mockery::mock(Connection::class);
    $connection->allows('getName')->andReturn('testing');
    $connection->allows('getDatabaseName')->andReturn(':memory:');

    $viewable = Mockery::mock(Viewable::class);
    $viewable->allows('getKey')->andReturn(7);
    $viewable->allows('getMorphClass')->andReturn('posts');
    $viewable->allows('getConnection')->andReturn($connection);

    return $viewable;
}

function reader(ViewSource $source, ?CacheRepository $cache = null, int $maxIntervals = 10_000): Reader
{
    return new Reader(
        $source,
        $cache ?? new CacheRepository(new ArrayStore),
        new Config(new Repository(['eloquent-viewable' => ['querying' => ['cache' => ['key' => 'views'], 'source' => ['driver' => 'database'], 'max_intervals' => $maxIntervals]]])),
    );
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-10 12:00:00');
    $this->viewable = readerViewable();
    $this->query = new ViewsQuery(Period::create('2026-09-01', '2026-09-03'), 'custom', true);
});

describe('count', function (): void {
    it('reads through the source', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('count')->with($this->viewable, $this->query)->andReturn(7);

        expect(reader($source)->count($this->viewable, $this->query))->toBe(7);
    });

    it('does not touch the cache without a lifetime', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('count')->twice()->andReturn(1, 2);

        $cache = new CacheRepository(new ArrayStore);
        $reader = reader($source, $cache);

        expect($reader->count($this->viewable, $this->query))->toBe(1)
            ->and($reader->count($this->viewable, $this->query))->toBe(2)
            ->and($cache->getStore()->all())->toBe([]);
    });

    it('remembers the count until the lifetime', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('count')->once()->andReturn(3);

        $reader = reader($source);
        $until = Carbon::now()->addMinutes(10);

        expect($reader->count($this->viewable, $this->query, $until))->toBe(3)
            ->and($reader->count($this->viewable, $this->query, $until))->toBe(3);

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $source->expects('count')->once()->andReturn(4);

        expect($reader->count($this->viewable, $this->query, Carbon::now()->addMinutes(10)))->toBe(4);
    });

    it('keeps a separate entry per query', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('count')->twice()->andReturn(3, 5);

        $reader = reader($source);
        $until = Carbon::now()->addMinutes(10);

        expect($reader->count($this->viewable, $this->query, $until))->toBe(3)
            ->and($reader->count($this->viewable, new ViewsQuery, $until))->toBe(5)
            ->and($reader->count($this->viewable, $this->query, $until))->toBe(3);
    });
});

describe('count by interval', function (): void {
    it('fills the series from the sparse counts of the source', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('countByInterval')->with($this->viewable, $this->query, Granularity::Day)->andReturn(['2026-09-02 00:00:00' => 4]);

        $series = reader($source)->countByInterval($this->viewable, $this->query, Granularity::Day);

        expect($series->intervals->map(fn (Bucket $bucket): int => $bucket->count)->all())->toBe([0, 4]);
    });

    it('requires a period with a start', function (ViewsQuery $query): void {
        $source = Mockery::mock(ViewSource::class);
        $source->shouldNotReceive('countByInterval');

        expect(fn (): ViewSeries => reader($source)->countByInterval($this->viewable, $query, Granularity::Day))
            ->toThrow(InvalidInterval::class, 'requires a period with a start date time');
    })->with([
        'no period' => [new ViewsQuery],
        'end only' => [new ViewsQuery(Period::upto('2026-09-03'))],
    ]);

    it('refuses more intervals than the cap', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->shouldNotReceive('countByInterval');

        expect(fn (): ViewSeries => reader($source, maxIntervals: 1)->countByInterval($this->viewable, $this->query, Granularity::Day))
            ->toThrow(InvalidInterval::class, '2 intervals, which exceeds the configured maximum of 1');
    });

    it('measures an open period up to now against the cap', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->shouldNotReceive('countByInterval');

        // 2026-09-01 up to 2026-09-10 12:00 is ten day buckets.
        expect(fn (): ViewSeries => reader($source, maxIntervals: 9)->countByInterval($this->viewable, new ViewsQuery(Period::since('2026-09-01')), Granularity::Day))
            ->toThrow(InvalidInterval::class, '10 intervals');
    });

    it('fills the series on the clock of the query timezone', function (): void {
        $query = new ViewsQuery(Period::create('2026-09-01', '2026-09-03'), timezone: new Timezone('Australia/Sydney'));

        $source = Mockery::mock(ViewSource::class);
        $source->expects('countByInterval')->with($this->viewable, $query, Granularity::Day)->andReturn(['2026-09-03 00:00:00' => 2]);

        $series = reader($source)->countByInterval($this->viewable, $query, Granularity::Day);

        expect($series->timezone->getName())->toBe('Australia/Sydney')
            ->and($series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('Y-m-d P'))->all())
            ->toBe(['2026-09-01 +10:00', '2026-09-02 +10:00', '2026-09-03 +10:00'])
            ->and($series->intervals->map(fn (Bucket $bucket): int => $bucket->count)->all())->toBe([0, 0, 2]);
    });

    it('measures the cap on the clock of the query timezone', function (): void {
        // One UTC day is two Sydney day buckets, 10:00 on the 1st to 10:00 on the 2nd.
        $period = Period::create('2026-09-01', '2026-09-02');

        $source = Mockery::mock(ViewSource::class);
        $source->expects('countByInterval')->once()->andReturn([]);

        expect(reader($source, maxIntervals: 1)->countByInterval($this->viewable, new ViewsQuery($period), Granularity::Day)->intervals)->toHaveCount(1)
            ->and(fn (): ViewSeries => reader($source, maxIntervals: 1)->countByInterval($this->viewable, new ViewsQuery($period, timezone: new Timezone('Australia/Sydney')), Granularity::Day))
            ->toThrow(InvalidInterval::class, '2 intervals');
    });

    it('remembers the sparse counts, not the filled series', function (): void {
        $source = Mockery::mock(ViewSource::class);
        $source->expects('countByInterval')->once()->andReturn(['2026-09-01 00:00:00' => 2]);

        $cache = new CacheRepository(new ArrayStore);
        $reader = reader($source, $cache);
        $until = Carbon::now()->addMinutes(10);

        $reader->countByInterval($this->viewable, $this->query, Granularity::Day, $until);
        $again = $reader->countByInterval($this->viewable, $this->query, Granularity::Day, $until);

        expect(array_values($cache->getStore()->all()))->toHaveCount(1)
            ->and($again->intervals->map(fn (Bucket $bucket): int => $bucket->count)->all())->toBe([2, 0]);
    });
});
