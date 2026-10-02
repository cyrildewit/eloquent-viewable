<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Visitors\Visitor;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

dataset('recording modes', [
    'synchronously' => [false],
    'queued' => [true],
]);

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is macroable', function (): void {
    Views::macro('newMethod', fn (): string => 'someValue');

    expect($this->app->make(Views::class)->newMethod())->toBe('someValue');
});

it('requires a viewable before it counts, records, attempts or destroys views', function (string $method): void {
    expect(fn (): mixed => $this->app->make(Views::class)->{$method}())
        ->toThrow(InvalidViewable::class, 'No viewable was given. Call forViewable() before counting, recording or destroying views.');
})->with(['count', 'record', 'attempt', 'destroy']);

describe('recording', function (): void {
    it('can record a view', function (): void {
        views($this->post)->record();

        expect(View::count())->toBe(1);
    });

    it('can record multiple views', function (): void {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect(View::count())->toBe(3);
    });

    it('throws an exception when recording a view for a viewable type', function (): void {
        expect(fn (): bool => views(new Post)
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record())->toThrow(RecordingFailed::class);
    });

    it('returns true when a view is recorded', function (): void {
        expect(views($this->post)->record())->toBeTrue();
    });

    it('returns false when a view is not recorded', function (): void {
        views($this->post)->cooldown(Carbon::now()->addMinutes(10))->record();

        expect(views($this->post)->cooldown(Carbon::now()->addMinutes(10))->record())->toBeFalse();
    });

    it('dispatches a ViewRecorded event when a view is recorded synchronously', function (): void {
        Event::fake();

        views($this->post)->record();

        Event::assertDispatched(ViewRecorded::class);
    });

    it('reports a stored view through attempt()', function (): void {
        $result = views($this->post)->attempt();

        expect($result)->toBeInstanceOf(RecordResult::class)
            ->and($result->recorded)->toBeTrue()
            ->and($result->queued)->toBeFalse()
            ->and($result->skippedBy)->toBeNull()
            ->and(View::count())->toBe(1);
    });

    it('reports a queued view through attempt()', function (): void {
        Bus::fake();

        $result = views($this->post)->queue()->attempt();

        expect($result->recorded)->toBeTrue()
            ->and($result->queued)->toBeTrue()
            ->and($result->skippedBy)->toBeNull();

        Bus::assertDispatched(RecordViewJob::class);
    });

    it('reports the guard that skipped the view through attempt()', function (): void {
        views($this->post)->cooldown(Carbon::now()->addMinutes(10))->record();

        $result = views($this->post)->cooldown(Carbon::now()->addMinutes(10))->attempt();

        expect($result->recorded)->toBeFalse()
            ->and($result->queued)->toBeFalse()
            ->and($result->skippedBy)->toBeInstanceOf(EnforceCooldown::class)
            ->and($result->wasSkippedBy(EnforceCooldown::class))->toBeTrue()
            ->and($result->wasSkippedBy(IgnoreCrawlers::class))->toBeFalse()
            ->and(View::count())->toBe(1);
    });
});

describe('queueing', function (): void {
    it('does not queue the view by default', function (): void {
        Bus::fake();

        views($this->post)->record();

        Bus::assertNotDispatched(RecordViewJob::class);
    });

    it('queues the view when queue() is used', function (): void {
        Bus::fake();

        $result = views($this->post)->queue()->record();

        expect($result)->toBeTrue();

        Bus::assertDispatched(RecordViewJob::class);
    });

    it('queues the view when enabled in the config', function (): void {
        Config::set('eloquent-viewable.recording.queue.enabled', true);

        Bus::fake();

        views($this->post)->record();

        Bus::assertDispatched(RecordViewJob::class);
    });

    it('can force synchronous recording when queueing is enabled in the config', function (): void {
        Config::set('eloquent-viewable.recording.queue.enabled', true);

        Bus::fake();

        views($this->post)->queue(false)->record();

        Bus::assertNotDispatched(RecordViewJob::class);

        expect(View::count())->toBe(1);
    });

    it('dispatches on the configured connection and queue', function (): void {
        Config::set('eloquent-viewable.recording.queue.connection', 'redis');
        Config::set('eloquent-viewable.recording.queue.queue', 'views');

        Bus::fake();

        views($this->post)->queue()->record();

        Bus::assertDispatched(RecordViewJob::class, fn (RecordViewJob $job): bool => $job->connection === 'redis' && $job->queue === 'views');
    });

    it('stores the view when the queued job is processed', function (): void {
        views($this->post)->queue()->collection('custom')->record();

        $view = View::sole();

        expect($view->viewable_id)->toBe($this->post->getKey())
            ->and($view->viewable_type)->toBe($this->post->getMorphClass())
            ->and($view->collection)->toBe('custom');
    });
});

describe('skipping views', function (): void {
    it('skips views from bots when the guard is listed', function (bool $queued): void {
        Config::set('eloquent-viewable.recording.guards', [IgnoreCrawlers::class]);

        $this->app->instance(CrawlerDetector::class, new class implements CrawlerDetector
        {
            public function isCrawler(?string $userAgent): bool
            {
                return true;
            }
        });

        Bus::fake();

        expect(views($this->post)->queue($queued)->record())->toBeFalse()
            ->and(View::count())->toBe(0);

        Bus::assertNothingDispatched();
    })->with('recording modes');

    it('skips views from visitors with the do not track header when the guard is listed', function (bool $queued): void {
        Config::set('eloquent-viewable.recording.guards', [IgnoreDoNotTrack::class]);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
        });

        Bus::fake();

        expect(views($this->post)->queue($queued)->record())->toBeFalse()
            ->and(View::count())->toBe(0);

        Bus::assertNothingDispatched();
    })->with('recording modes');

    it('skips views from ignored ip addresses when the guard is listed', function (bool $queued): void {
        Config::set('eloquent-viewable.recording.guards', [IgnoreIpAddresses::class]);
        Config::set('eloquent-viewable.recording.ignored_ip_addresses', ['127.20.22.6', '10.10.30.40']);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('ip')->andReturn('127.20.22.6');
        });

        Bus::fake();

        expect(views($this->post)->queue($queued)->record())->toBeFalse()
            ->and(View::count())->toBe(0);

        Bus::assertNothingDispatched();
    })->with('recording modes');

    it('skips views while a cooldown is active', function (bool $queued): void {
        Bus::fake();

        expect(views($this->post)->queue($queued)->cooldown(Carbon::now()->addMinutes(10))->record())->toBeTrue()
            ->and(views($this->post)->queue($queued)->cooldown(Carbon::now()->addMinutes(10))->record())->toBeFalse()
            ->and(View::count())->toBe($queued ? 0 : 1);

        Bus::assertDispatchedTimes(RecordViewJob::class, $queued ? 1 : 0);
    })->with('recording modes');
});

describe('cooldowns', function (): void {
    it('can record a view with cooldown where lifetime is an integer', function (): void {
        views($this->post)
            ->cooldown(10)
            ->record();

        views($this->post)
            ->cooldown(10)
            ->record();

        expect(View::count())->toBe(1);
    });

    it('does not record views if cooldown is active with collection', function (): void {
        views($this->post)
            ->collection('test')
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        views($this->post)
            ->collection('test')
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        expect(View::count())->toBe(1);
    });

    it('can remove a cooldown', function (): void {
        views($this->post)
            ->cooldown(null)
            ->record();

        views($this->post)
            ->cooldown(null)
            ->record();

        expect(View::count())->toBe(2);
    });
});

describe('collections', function (): void {
    it('can record a view under a collection', function (): void {
        views($this->post)
            ->collection('customCollection')
            ->record();

        views($this->post)
            ->record();

        expect(View::where('collection', 'customCollection')->count())->toBe(1);
    });

    it('can remove the collection', function (): void {
        views($this->post)
            ->collection(null)
            ->record();

        views($this->post)
            ->record();

        expect(View::where('collection', null)->count())->toBe(2);
    });
});

describe('counting', function (): void {
    it('can count the views', function (): void {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect($this->post)->toHaveViewsCount(3);
    });

    it('can count the unique views', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

        expect($this->post)->toHaveUniqueViewsCount(2);
    });

    it('can count the views of a period', function (): void {
        $this->freezeTime();

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-01-10'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-01-15'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-02-10'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-02-15'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-03-10'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-03-15'))->create();

        // Periods are half-open, so a view recorded exactly at the end is excluded.
        expect(views($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count())->toBe(6)
            ->and(views($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count())->toBe(3)
            ->and(views($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count())->toBe(3);
    });

    it('can remove the period', function (): void {
        $this->freezeTime();

        View::factory()->for($this->post, 'viewable')->count(2)->create();

        expect(views($this->post)->period(null)->count())->toBe(2);
    });

    it('can count the views with a collection', function (): void {
        views($this->post)->collection('custom')->record();
        views($this->post)->collection('custom')->record();
        views($this->post)->record();

        expect(views($this->post)->collection('custom')->count())->toBe(2)
            ->and(views($this->post)->count())->toBe(3);
    });

    it('can count the views by type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        View::factory()->for($postOne, 'viewable')->create();
        View::factory()->for($postTwo, 'viewable')->count(2)->create();
        View::factory()->for($apartment, 'viewable')->count(2)->create();

        expect(new Post)->toHaveViewsCount(3);
    });

    it('can count the unique views by type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        View::factory()->for($postOne, 'viewable')->fromVisitor('visitor_one')->create();
        View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->create();
        View::factory()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
        View::factory()->for($apartment, 'viewable')->fromVisitor('visitor_three')->create();
        View::factory()->for($apartment, 'viewable')->fromVisitor('visitor_one')->create();

        expect(new Post)->toHaveUniqueViewsCount(2);
    });
});

describe('counting by interval', function (): void {
    function counts(ViewSeries $series): array
    {
        return $series->intervals->map(fn (Bucket $bucket): int => $bucket->count)->all();
    }

    it('counts per {granularity} with empty buckets filled with zero', function (Granularity $granularity, Period $period, array $viewedAt, array $expected): void {
        foreach ($viewedAt as $dateTime) {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
        }

        $series = views($this->post)->period($period)->countByInterval($granularity);

        expect($series)->toBeInstanceOf(ViewSeries::class)
            ->and($series->intervals)->toHaveSameSize($expected)
            ->and(counts($series))->toBe($expected);
    })->with([
        'hour' => [Granularity::Hour, Period::create('2026-09-01 00:00:00', '2026-09-01 04:00:00'), ['2026-09-01 00:10:00', '2026-09-01 00:50:00', '2026-09-01 02:30:00'], [2, 0, 1, 0]],
        'day' => [Granularity::Day, Period::create('2026-09-01', '2026-09-06'), ['2026-09-01 12:00:00', '2026-09-04 08:00:00', '2026-09-04 20:00:00'], [1, 0, 0, 2, 0]],
        'week' => [Granularity::Week, Period::create('2026-08-31', '2026-09-21'), ['2026-09-02', '2026-09-03', '2026-09-14'], [2, 0, 1]],
        'month' => [Granularity::Month, Period::create('2026-06-01', '2026-09-01'), ['2026-06-15', '2026-08-01', '2026-08-31 23:59:59'], [1, 0, 2]],
        'year' => [Granularity::Year, Period::create('2024-01-01', '2027-01-01'), ['2024-05-01', '2026-01-01'], [1, 0, 1]],
    ]);

    it('returns the buckets in chronological order, each ending where the next starts', function (): void {
        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-06'))->countByInterval(Granularity::Day);

        $intervals = $series->intervals->all();

        foreach (array_slice($intervals, 0, -1) as $index => $bucket) {
            expect($bucket->end)->toEqual($intervals[$index + 1]->start)
                ->and($bucket->start)->toBeLessThan($bucket->end);
        }
    });

    it('sums to the plain count over the same period', function (): void {
        Carbon::setTestNow('2026-09-10 12:00:00');

        foreach (['2026-09-01 12:00:00', '2026-09-02 12:00:00', '2026-09-02 13:00:00', '2026-09-09 12:00:00'] as $dateTime) {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
        }

        $period = Period::create('2026-09-01', '2026-09-10');

        expect(views($this->post)->period($period)->countByInterval(Granularity::Day)->total())
            ->toBe(views($this->post)->period($period)->count());
    });

    it('lets a bucket drill down into the same count', function (): void {
        foreach (['2026-09-02 00:00:00', '2026-09-02 12:00:00', '2026-09-02 23:59:59', '2026-09-03 00:00:00'] as $dateTime) {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
        }

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-05'))->countByInterval(Granularity::Day);

        foreach ($series as $bucket) {
            expect(views($this->post)->period($bucket->period())->count())->toBe($bucket->count);
        }

        expect(counts($series))->toBe([0, 3, 1, 0]);
    });

    it('counts unique visitors per bucket and ignores null visitors', function (): void {
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-01 09:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->state(['visitor' => null])->viewedAt(Carbon::parse('2026-09-02 10:00:00'))->create();

        $period = Period::create('2026-09-01', '2026-09-03');

        expect(counts(views($this->post)->period($period)->unique()->countByInterval(Granularity::Day)))->toBe([2, 0])
            ->and(counts(views($this->post)->period($period)->countByInterval(Granularity::Day)))->toBe([3, 1]);
    });

    it('filters on the collection', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('custom')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-02 08:00:00'))->create();

        $period = Period::create('2026-09-01', '2026-09-03');

        expect(counts(views($this->post)->period($period)->collection('custom')->countByInterval(Granularity::Day)))->toBe([1, 0]);
    });

    it('counts every viewable of a type', function (): void {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->viewedAt(Carbon::parse('2026-09-02 08:00:00'))->create();
        View::factory()->for(Apartment::factory()->create(), 'viewable')->viewedAt(Carbon::parse('2026-09-02 09:00:00'))->create();

        $period = Period::create('2026-09-01', '2026-09-03');

        expect(counts(views(Post::class)->period($period)->countByInterval(Granularity::Day)))->toBe([1, 1]);
    });

    it('treats a missing period end as now', function (): void {
        Carbon::setTestNow('2026-09-03 12:00:00');

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-03 09:00:00'))->create();

        $series = views($this->post)->period(Period::pastDays(2))->countByInterval(Granularity::Day);

        expect(counts($series))->toBe([1, 0, 1]);
    });

    it('counts the same rows for period bounds carried in another timezone', function (): void {
        foreach (['2026-09-27 00:30:00', '2026-09-27 23:30:00', '2026-09-28 12:00:00'] as $dateTime) {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
        }

        // The same two instants, handed over as Amsterdam wall clocks instead
        // of the application's own.
        $elsewhere = Period::create(
            Carbon::parse('2026-09-27 00:00:00')->setTimezone('Europe/Amsterdam'),
            Carbon::parse('2026-09-29 00:00:00')->setTimezone('Europe/Amsterdam'),
        );

        expect(counts(views($this->post)->period($elsewhere)->countByInterval(Granularity::Day)))
            ->toBe(counts(views($this->post)->period(Period::create('2026-09-27', '2026-09-29'))->countByInterval(Granularity::Day)))
            ->and(counts(views($this->post)->period($elsewhere)->countByInterval(Granularity::Day)))->toBe([2, 1]);
    });

    it('throws without a period', function (): void {
        expect(fn (): ViewSeries => views($this->post)->countByInterval(Granularity::Day))
            ->toThrow(InvalidInterval::class);
    });

    it('throws for a period without a start', function (): void {
        expect(fn (): ViewSeries => views($this->post)->period(Period::upto('2026-09-01'))->countByInterval(Granularity::Day))
            ->toThrow(InvalidInterval::class);
    });

    it('allows exactly the configured maximum number of intervals', function (): void {
        Config::set('eloquent-viewable.querying.max_intervals', 3);

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-04'))->countByInterval(Granularity::Day);

        expect($series->intervals)->toHaveCount(3);
    });

    it('throws over the configured maximum number of intervals', function (): void {
        Config::set('eloquent-viewable.querying.max_intervals', 3);

        expect(fn (): ViewSeries => views($this->post)->period(Period::create('2026-09-01', '2026-09-05'))->countByInterval(Granularity::Day))
            ->toThrow(InvalidInterval::class, '4 intervals');
    });

    it('does not query the database when over the maximum', function (): void {
        Config::set('eloquent-viewable.querying.max_intervals', 3);

        DB::enableQueryLog();

        expect(fn (): ViewSeries => views($this->post)->period(Period::create('2026-09-01', '2026-09-05'))->countByInterval(Granularity::Day))
            ->toThrow(InvalidInterval::class)
            ->and(DB::getQueryLog())->toBeEmpty();
    });

    it('can remember the series', function (): void {
        $period = Period::create('2026-09-01', '2026-09-03');

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();

        expect(counts(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)))->toBe([1, 0]);

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-02 08:00:00'))->create();

        expect(counts(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)))->toBe([1, 0])
            ->and(counts(views($this->post)->period($period)->countByInterval(Granularity::Day)))->toBe([1, 1]);
    });

    it('remembers an empty series', function (): void {
        $period = Period::create('2026-09-01', '2026-09-03');

        expect(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)->total())->toBe(0);

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();

        expect(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)->total())->toBe(0);
    });

    it('does not share a cache entry between granularities', function (): void {
        $period = Period::create('2026-09-01', '2026-09-03');

        expect(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Hour)->intervals)->toHaveCount(48)
            ->and(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)->intervals)->toHaveCount(2);
    });

    it('does not share a cache entry with the plain count', function (): void {
        $period = Period::create('2026-09-01', '2026-09-03');

        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 08:00:00'))->create();

        expect(views($this->post)->period($period)->remember(60)->count())->toBe(1)
            ->and(counts(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)))->toBe([1, 0]);
    });

    it('reads through the ViewSource bound in the container', function (): void {
        $this->app->bind(ViewSource::class, fn (): ViewSource => new class implements ViewSource
        {
            public function count(Viewable $viewable, ViewsQuery $query): int
            {
                return 7;
            }

            public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
            {
                return ['2026-09-01 00:00:00' => 42];
            }

            public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
            {
                return DB::query()->selectRaw('0');
            }
        });

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->countByInterval(Granularity::Day);

        expect(views($this->post)->count())->toBe(7)
            ->and(counts($series))->toBe([42, 0]);
    });

    describe('in a non-UTC application timezone', function (): void {
        beforeEach(function (): void {
            $this->timezone = date_default_timezone_get();
            date_default_timezone_set('Europe/Amsterdam');
        });

        afterEach(function (): void {
            date_default_timezone_set($this->timezone);
        });

        it('labels buckets the same way the SQL does', function (): void {
            foreach (['2026-07-01 00:30:00', '2026-07-01 23:30:00', '2026-07-03 12:00:00'] as $dateTime) {
                View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
            }

            $period = Period::create('2026-07-01', '2026-07-04');
            $series = views($this->post)->period($period)->countByInterval(Granularity::Day);

            // A label the SQL emits but the series never generates would leave
            // every bucket at zero while the plain count still finds the rows.
            expect(counts($series))->toBe([2, 0, 1])
                ->and($series->total())->toBe(views($this->post)->period($period)->count())
                ->and($series->total())->toBe(3);
        });

        it('keeps both real hours of an ambiguous wall clock in one bucket', function (): void {
            // Amsterdam puts the clock back an hour at 03:00 CEST on this date,
            // so 02:30 happens twice: once at +02:00 and once at +01:00.
            $duringCest = Carbon::parse('2026-10-25 00:30:00', 'UTC')->setTimezone('Europe/Amsterdam');
            $duringCet = Carbon::parse('2026-10-25 01:30:00', 'UTC')->setTimezone('Europe/Amsterdam');

            expect($duringCest->format('H:i P'))->toBe('02:30 +02:00')
                ->and($duringCet->format('H:i P'))->toBe('02:30 +01:00');

            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-10-25 01:30:00'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt($duringCest)->create();
            View::factory()->for($this->post, 'viewable')->viewedAt($duringCet)->create();

            $period = Period::create('2026-10-25 00:00:00', '2026-10-25 04:00:00');
            $series = views($this->post)->period($period)->countByInterval(Granularity::Hour);

            expect(counts($series))->toBe([0, 1, 2, 0])
                ->and($series->total())->toBe(views($this->post)->period($period)->count());
        });

        it('leaves the hour skipped by the spring transition empty', function (): void {
            // 02:00 does not exist in Amsterdam on this date. The bucket is
            // still emitted so the series stays one bucket per hour label.
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-03-29 01:30:00'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-03-29 03:30:00'))->create();

            $period = Period::create('2026-03-29 00:00:00', '2026-03-29 04:00:00');
            $series = views($this->post)->period($period)->countByInterval(Granularity::Hour);

            expect(counts($series))->toBe([0, 1, 0, 1])
                ->and($series->total())->toBe(views($this->post)->period($period)->count());
        });
    });

    describe('in another timezone', function (): void {
        it('aligns day buckets to that clock', function (): void {
            // 13:00 UTC is 23:00 in Sydney on the same day; 15:00 UTC is 01:00 the next.
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 13:00:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-03 00:00:00', 'UTC'));

            $series = views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Day);

            expect(counts(views($this->post)->period($period)->countByInterval(Granularity::Day)))->toBe([2, 0])
                ->and(counts($series))->toBe([1, 1, 0])
                ->and($series->timezone->getName())->toBe('Australia/Sydney')
                ->and($series->intervals->first()->start->format('Y-m-d H:i P'))->toBe('2026-09-01 00:00 +10:00')
                ->and($series->total())->toBe(views($this->post)->period($period)->count());
        });

        it('re-anchors a relative period on that clock', function (): void {
            // 23:00 UTC on the 1st is 09:00 on the 2nd in Sydney, so "yesterday"
            // in Sydney is the 1st, and a UTC-anchored period would start on the 31st.
            Carbon::setTestNow('2026-09-01 23:00:00');

            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-08-31 15:00:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $series = views($this->post)->period(Period::pastDays(1))->timezone('Australia/Sydney')->countByInterval(Granularity::Day);

            expect($series->period->getStartDateTime()->timestamp)->toBe(Carbon::parse('2026-09-01 00:00:00', 'Australia/Sydney')->timestamp)
                ->and($series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('Y-m-d'))->all())->toBe(['2026-09-01', '2026-09-02'])
                ->and(counts($series))->toBe([1, 1])
                ->and(views($this->post)->period(Period::pastDays(1))->timezone('Australia/Sydney')->count())->toBe(2)
                ->and(views($this->post)->period(Period::pastDays(1))->count())->toBe(2);
        });

        it('accepts a DateTimeZone', function (): void {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-02 00:00:00', 'UTC'));

            expect(counts(views($this->post)->period($period)->timezone(new DateTimeZone('Australia/Sydney'))->countByInterval(Granularity::Day)))->toBe([0, 1]);
        });

        it('drills from a bucket into the same count', function (): void {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 13:00:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-03 00:00:00', 'UTC'));

            foreach (views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Day) as $bucket) {
                expect(views($this->post)->period($bucket->period())->count())->toBe($bucket->count);
            }
        });

        it('follows the spring transition of that zone', function (): void {
            // Sydney skips 02:00 on 2026-10-04, at 2026-10-03 16:00 UTC. The
            // rows straddle it, so the offsets differ on either side.
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-10-03 15:30:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-10-03 16:30:00', 'UTC'))->create();

            // 14:00 UTC is 00:00 AEST; 17:00 UTC is already 04:00 AEDT.
            $period = Period::create(Carbon::parse('2026-10-03 14:00:00', 'UTC'), Carbon::parse('2026-10-03 17:00:00', 'UTC'));

            $series = views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Hour);
            $skipped = $series->intervals[2];

            expect(counts($series))->toBe([0, 1, 0, 1])
                ->and($skipped->start->diffInMinutes($skipped->end))->toBe(0.0)
                ->and($series->total())->toBe(views($this->post)->period($period)->count());
        });

        it('keeps both real hours of the ambiguous wall clock of that zone in one bucket', function (): void {
            // Sydney repeats 02:00 on 2026-04-05, at 2026-04-04 16:00 UTC.
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-04-04 15:30:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-04-04 16:30:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-04-04 13:00:00', 'UTC'), Carbon::parse('2026-04-04 17:00:00', 'UTC'));

            $series = views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Hour);

            expect($series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('H:i'))->all())->toBe(['00:00', '01:00', '02:00'])
                ->and(counts($series))->toBe([0, 0, 2])
                ->and($series->total())->toBe(views($this->post)->period($period)->count());
        });

        it('counts the same rows as without a timezone when the clocks agree', function (): void {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 13:00:00', 'UTC'))->create();
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-02 15:00:00', 'UTC'))->create();

            $period = Period::create('2026-09-01', '2026-09-03');

            expect(counts(views($this->post)->period($period)->timezone(date_default_timezone_get())->countByInterval(Granularity::Day)))
                ->toBe(counts(views($this->post)->period($period)->countByInterval(Granularity::Day)));
        });

        it('keeps a separate cache entry per timezone', function (): void {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-02 00:00:00', 'UTC'));

            expect(counts(views($this->post)->period($period)->remember(60)->countByInterval(Granularity::Day)))->toBe([1])
                ->and(counts(views($this->post)->period($period)->remember(60)->timezone('Australia/Sydney')->countByInterval(Granularity::Day)))->toBe([0, 1]);
        });

        it('rejects a timezone that is not an identifier', function (): void {
            expect(fn (): Views => views($this->post)->timezone('+10:00'))
                ->toThrow(InvalidTimezone::class, '`+10:00` is not a timezone identifier');
        });

        it('can be cleared again', function (): void {
            View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-01 15:00:00', 'UTC'))->create();

            $period = Period::create(Carbon::parse('2026-09-01 00:00:00', 'UTC'), Carbon::parse('2026-09-02 00:00:00', 'UTC'));

            expect(counts(views($this->post)->period($period)->timezone('Australia/Sydney')->timezone(null)->countByInterval(Granularity::Day)))->toBe([1]);
        });

        describe('from a non-UTC application timezone', function (): void {
            beforeEach(function (): void {
                $this->timezone = date_default_timezone_get();
                date_default_timezone_set('Europe/Amsterdam');
            });

            afterEach(function (): void {
                date_default_timezone_set($this->timezone);
            });

            it('converts across the fall-back transition of the storage zone', function (): void {
                // Amsterdam falls back on 2026-10-25. 15:30 CEST on the 24th is
                // 00:30 on the 25th in Sydney; 14:30 CET on the 26th is 00:30 on the 27th.
                View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-10-24 15:30:00'))->create();
                View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-10-26 14:30:00'))->create();

                $period = Period::create('2026-10-24', '2026-10-27');

                $series = views($this->post)->period($period)->timezone('Australia/Sydney')->countByInterval(Granularity::Day);

                expect($series->intervals->map(fn (Bucket $bucket): string => $bucket->start->format('Y-m-d'))->all())
                    ->toBe(['2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27'])
                    ->and(counts($series))->toBe([0, 1, 0, 1])
                    ->and($series->total())->toBe(views($this->post)->period($period)->count());
            });
        });
    });

    it('range-scans the composite index', function (): void {
        DB::enableQueryLog();

        views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->countByInterval(Granularity::Day);

        $query = DB::getQueryLog()[0];
        $plan = collect(DB::select('explain query plan '.$query['query'], $query['bindings']))->pluck('detail')->implode(' ');

        // SQLite reports "USING INDEX" or "USING COVERING INDEX"; both range-scan it.
        expect($plan)->toContain('INDEX views_viewable_viewed_at_index (viewable_type=? AND viewable_id=? AND viewed_at>? AND viewed_at<?)');
    })->skip(fn (): bool => driver() !== 'sqlite', 'Query plans are asserted on SQLite only');
});

describe('destroying', function (): void {
    it('can destroy the views', function (): void {
        $post = $this->post;
        $apartment = Apartment::factory()->create();

        View::factory()->for($post, 'viewable')->count(4)->create();
        View::factory()->for($apartment, 'viewable')->count(2)->create();

        views($post)->destroy();

        expect($post)->toHaveViewsCount(0);
    });

    it('can destroy the views of a viewable type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        View::factory()->for($postOne, 'viewable')->count(3)->create();
        View::factory()->for($postTwo, 'viewable')->count(2)->create();
        View::factory()->for($apartment, 'viewable')->count(2)->create();

        views(new Post)->destroy();

        expect(new Post)->toHaveViewsCount(0);
    });
});

describe('remembering', function (): void {
    it('can remember the views counts', function (): void {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember(60)->count())->toBe(3);
    });

    it('can remove the remember lifetime', function (): void {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember(60)->remember()->count())->toBe(5);
    });

    it('can remember the views counts with a custom lifetime', function (DateTimeInterface|int $lifetime): void {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember($lifetime)->count())->toBe(3);

        views($this->post)->record();
        views($this->post)->record();

        expect(views($this->post)->remember($lifetime)->count())->toBe(3);
    })->with([
        'integer' => 10,
        'DateTime interface' => new DateTime('2050-01-01'),
        'Carbon interface' => Carbon::now()->addHours(2),
    ]);

    it('throws an exception when remember lifetime is of incorrect type', function (): void {
        expect(fn (): int => views($this->post)->remember('not good')->count())
            ->toThrow(TypeError::class);
    });

    it('can remember the views counts of a type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        views($postOne)->record();
        views($postTwo)->record();
        views($postTwo)->record();
        views($apartment)->record();
        views($apartment)->record();

        expect(views(Post::class)->remember(60)->count())->toBe(3);

        views($postTwo)->record();
        views($apartment)->record();

        expect(views(Post::class)->remember(60)->count())->toBe(3);
    });

    it('remembers the views counts in the configured cache store', function (): void {
        Config::set('cache.stores.views', ['driver' => 'array']);
        Config::set('eloquent-viewable.querying.cache.store', 'views');

        View::factory()->for($this->post, 'viewable')->count(3)->create();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        View::factory()->for($this->post, 'viewable')->count(2)->create();

        // Flushing the default store must not touch the remembered count.
        Cache::flush();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        Cache::store('views')->flush();

        expect(views($this->post)->remember(60)->count())->toBe(5);
    });
});

describe('visitor handling', function (): void {
    it('does not record views from a crawler user agent when the guard is listed', function (string $userAgent, bool $recorded): void {
        Config::set('eloquent-viewable.recording.guards', [IgnoreCrawlers::class]);

        $this->app['request']->headers->set('User-Agent', $userAgent);

        expect(views($this->post)->record())->toBe($recorded)
            ->and(View::count())->toBe($recorded ? 1 : 0);
    })->with([
        'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', false],
        'Chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', true],
    ]);

    it('can set the visitor instance', function (): void {
        Config::set('eloquent-viewable.recording.guards', [IgnoreCrawlers::class]);

        // Any implementation of the contract will do, not only the shipped class.
        $crawler = Mockery::mock(VisitorContract::class);
        $crawler->shouldReceive('userAgent')
            ->andReturn('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        views($this->post)->record();

        views($this->post)->useVisitor($crawler)->record();

        views($this->post)->record();

        expect(View::count())->toBe(2);
    });
});
