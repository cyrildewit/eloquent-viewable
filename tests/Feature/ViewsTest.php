<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Exceptions\InvalidTimezone;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewer;
use CyrildeWit\EloquentViewable\Facades\Views as ViewsFacade;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidFrequency;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidLimit;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidReturning;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Frequency\VisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Ranking\Entry;
use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Querying\Series\Bucket;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;
use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Recording\Events\ViewsDestroyed;
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
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\KeepsViewsPost;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor as VisitorContract;
use CyrildeWit\EloquentViewable\Visitors\Visitor;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
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

function visitorWithId(string $id): VisitorContract
{
    $visitor = Mockery::mock(VisitorContract::class);
    $visitor->allows('id')->andReturn($id);
    $visitor->allows('viewer')->andReturn(null);
    $visitor->allows('ip')->andReturn('127.0.0.1');
    $visitor->allows('userAgent')->andReturn('Mozilla/5.0');
    $visitor->allows('hasDoNotTrackHeader')->andReturn(false);
    $visitor->allows('hasGlobalPrivacyControl')->andReturn(false);
    $visitor->allows('isPrefetch')->andReturn(false);
    $visitor->allows('isHeadRequest')->andReturn(false);

    return $visitor;
}

it('is macroable', function (): void {
    Views::macro('newMethod', fn (): string => 'someValue');

    expect($this->app->make(Views::class)->newMethod())->toBe('someValue');
});

it('starts every facade call with a fresh builder', function (): void {
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-01-10'))->create();
    View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-02-10'))->create();

    expect(ViewsFacade::forViewable($this->post)->period(Period::since('2026-02-01'))->count())->toBe(1)
        ->and(ViewsFacade::forViewable($this->post)->count())->toBe(2)
        ->and(ViewsFacade::getFacadeRoot())->not->toBe(ViewsFacade::getFacadeRoot())
        ->and(fn (): int => ViewsFacade::count())->toThrow(InvalidViewable::class);
});

it('keeps a double set on the facade', function (): void {
    ViewsFacade::shouldReceive('count')->twice()->andReturn(42);

    expect(ViewsFacade::count())->toBe(42)
        ->and(ViewsFacade::count())->toBe(42);
});

it('requires a viewable before it counts, records, attempts or destroys views', function (string $method): void {
    expect(fn (): mixed => $this->app->make(Views::class)->{$method}())
        ->toThrow(InvalidViewable::class, 'No viewable was given. Call forViewable() before counting, recording or destroying views.');
})->with(['count', 'record', 'attempt', 'destroy', 'forgetCache']);

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

describe('viewers', function (): void {
    it('records a guest view by default', function (): void {
        $this->actingAs(User::factory()->create());

        views($this->post)->record();

        expect(View::sole()->viewer)->toBeNull();
    });

    it('records the signed-in user when enabled', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        $user = User::factory()->create();
        $this->actingAs($user);

        views($this->post)->record();

        expect(View::sole()->viewer->is($user))->toBeTrue();
    });

    it('records a guest view for a guest when enabled', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);

        views($this->post)->record();

        expect(View::sole()->viewer_type)->toBeNull();
    });

    it('reads the signed-in user from the configured guard', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        Config::set('eloquent-viewable.recording.viewer.guard', 'admin');
        Config::set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $admin = User::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->actingAs($admin, 'admin');

        views($this->post)->record();

        expect(View::sole()->viewer->is($admin))->toBeTrue();
    });

    it('records the viewer given to viewedBy() whether or not it is enabled', function (): void {
        $user = User::factory()->create();

        views($this->post)->viewedBy($user)->record();

        expect(View::sole()->viewer->is($user))->toBeTrue();
    });

    it('prefers the viewer given to viewedBy() over the signed-in user', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        $user = User::factory()->create();
        $this->actingAs(User::factory()->create());

        views($this->post)->viewedBy($user)->record();

        expect(View::sole()->viewer->is($user))->toBeTrue();
    });

    it('links any Eloquent model as the viewer', function (): void {
        $apartment = Apartment::factory()->create();

        views($this->post)->viewedBy($apartment)->record();

        expect(View::sole()->viewer)->toBeInstanceOf(Apartment::class);
    });

    it('clears the viewer with viewedBy(null)', function (): void {
        views($this->post)->viewedBy(User::factory()->create())->viewedBy(null)->record();

        expect(View::sole()->viewer_type)->toBeNull();
    });

    it('keeps the viewer when the view is queued', function (): void {
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        $user = User::factory()->create();
        $this->actingAs($user);

        views($this->post)->queue()->record();

        expect(View::sole()->viewer->is($user))->toBeTrue();
    });

    it('dispatches the event with the viewer on the record', function (): void {
        Event::fake([ViewRecorded::class]);
        $user = User::factory()->create();

        views($this->post)->viewedBy($user)->record();

        Event::assertDispatched(ViewRecorded::class, fn (ViewRecorded $event): bool => $event->record->viewerType === $user->getMorphClass() && $event->record->viewerId === $user->getKey());
    });

    it('counts the views of one viewer', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($user)->count(2)->create();
        View::factory()->for($this->post, 'viewable')->by($other)->create();
        View::factory()->for($this->post, 'viewable')->create();

        expect(views($this->post)->viewedBy($user)->count())->toBe(2)
            ->and(views($this->post)->viewedBy($other)->count())->toBe(1)
            ->and(views($this->post)->viewedBy($user)->viewedBy(null)->count())->toBe(4)
            ->and(views(new Post)->viewedBy($user)->count())->toBe(2);
    });

    it('counts the views of one viewer by interval', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($user)->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-02 10:00:00'))->create();

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->viewedBy($user)->countByInterval(Granularity::Day);

        expect($series->intervals->pluck('count')->all())->toBe([1, 0]);
    });

    it('does not share a cache entry between viewers', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($user)->create();
        View::factory()->for($this->post, 'viewable')->create();

        expect(views($this->post)->remember()->count())->toBe(2)
            ->and(views($this->post)->viewedBy($user)->remember()->count())->toBe(1);
    });
});

describe('visitor identity', function (): void {
    it('keeps the cookie id as the visitor by default', function (): void {
        $user = User::factory()->create();

        views($this->post)->viewedBy($user)->record();

        expect(View::sole()->visitor)->toHaveLength(80);
    });

    it('counts one account on many devices as one unique visitor', function (): void {
        Config::set('eloquent-viewable.visitor.identity', 'viewer');
        $user = User::factory()->create();

        views($this->post)->viewedBy($user)->useVisitor(visitorWithId('laptop'))->record();
        views($this->post)->viewedBy($user)->useVisitor(visitorWithId('phone'))->record();
        views($this->post)->useVisitor(visitorWithId('guest'))->record();

        expect($this->post)->toHaveViewsCount(3)
            ->toHaveUniqueViewsCount(2)
            ->and(View::byViewer($user)->pluck('visitor')->unique())->toHaveCount(1)
            ->and(View::byViewer($user)->first()->visitor)->toBe($this->app->make(VisitorIdentity::class)->ofViewer($user))
            ->and(View::whereNull('viewer_id')->sole()->visitor)->toBe('guest');
    });

    it('holds a cooldown across devices for one account', function (): void {
        Config::set('eloquent-viewable.visitor.identity', 'viewer');
        $user = User::factory()->create();

        expect(views($this->post)->viewedBy($user)->useVisitor(visitorWithId('laptop'))->cooldown(10)->record())->toBeTrue()
            ->and(views($this->post)->viewedBy($user)->useVisitor(visitorWithId('phone'))->cooldown(10)->record())->toBeFalse()
            ->and(views($this->post)->useVisitor(visitorWithId('phone'))->cooldown(10)->record())->toBeTrue();
    });

    it('derives the visitor from the signed-in user when both are enabled', function (): void {
        Config::set('eloquent-viewable.visitor.identity', 'viewer');
        Config::set('eloquent-viewable.recording.viewer.enabled', true);
        $user = User::factory()->create();
        $this->actingAs($user);

        views($this->post)->record();

        expect(View::sole()->visitor)->toBe($this->app->make(VisitorIdentity::class)->ofViewer($user));
    });

    it('answers whether a user has seen a model through the visitor scopes', function (): void {
        Config::set('eloquent-viewable.visitor.identity', 'viewer');
        $user = User::factory()->create();
        $unseen = Post::factory()->create();

        views($this->post)->viewedBy($user)->record();

        $visitor = $this->app->make(VisitorIdentity::class)->ofViewer($user);

        expect(Post::whereViewedByVisitor($visitor)->pluck('id'))->toEqual(keysOf($this->post))
            ->and(Post::whereNotViewedByVisitor($visitor)->pluck('id'))->toEqual(keysOf($unseen));
    });
});

describe('context', function (): void {
    it('records the context as json', function (): void {
        views($this->post)->context(['source' => 'newsletter', 'campaign' => 42])->record();

        // MySQL stores a JSON object with its keys sorted, so the order is not asserted.
        expect(View::sole()->context)->toEqual(['source' => 'newsletter', 'campaign' => 42]);
    });

    it('records no context by default', function (): void {
        views($this->post)->record();

        expect(View::sole()->context)->toBeNull();
    });

    it('clears the context with context(null)', function (): void {
        views($this->post)->context(['source' => 'newsletter'])->context(null)->record();

        expect(View::sole()->context)->toBeNull();
    });

    it('keeps the context when the view is queued', function (): void {
        views($this->post)->queue()->context(['source' => 'newsletter'])->record();

        expect(View::sole()->context)->toBe(['source' => 'newsletter']);
    });

    it('can be queried with the json path syntax', function (): void {
        views($this->post)->context(['source' => 'newsletter'])->record();
        views($this->post)->context(['source' => 'search'])->record();
        views($this->post)->record();

        expect($this->post->views()->where('context->source', 'newsletter')->count())->toBe(1);
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

describe('comparing', function (): void {
    function viewedAt(Viewable $viewable, string ...$dateTimes): void
    {
        foreach ($dateTimes as $dateTime) {
            View::factory()->for($viewable, 'viewable')->viewedAt(Carbon::parse($dateTime))->create();
        }
    }

    beforeEach(function (): void {
        Carbon::setTestNow('2026-09-10 12:00:00');
    });

    it('compares the period with the one before it', function (): void {
        viewedAt($this->post, '2026-08-26 23:59:59', '2026-08-27 00:00:00', '2026-09-02 23:59:59', '2026-09-03 00:00:00', '2026-09-05 12:00:00', '2026-09-10 08:00:00');

        $comparison = views($this->post)->period(Period::pastDays(7))->compare();

        expect($comparison)->toBeInstanceOf(ViewComparison::class)
            ->and($comparison->toArray())->toBe(['current' => 3, 'previous' => 2, 'delta' => 1, 'percent' => 50.0])
            ->and($comparison->previousPeriod->getRouteKey())->toBe('2026-08-27..2026-09-03');
    });

    it('splits an absolute period at its start', function (): void {
        viewedAt($this->post, '2026-08-31 23:59:59', '2026-09-01 00:00:00', '2026-09-05 00:00:00');

        $comparison = views($this->post)->period(Period::create('2026-09-03', '2026-09-05'))->compare();

        expect($comparison->toArray())->toBe(['current' => 0, 'previous' => 1, 'delta' => -1, 'percent' => -100.0]);
    });

    it('reads both periods as unique visitors in a collection for one viewer', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('one')->viewedAt(Carbon::parse('2026-09-04'))->count(2)->create();
        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('one')->viewedAt(Carbon::parse('2026-08-28'))->create();
        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('two')->viewedAt(Carbon::parse('2026-08-28'))->create();
        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('three')->viewedAt(Carbon::parse('2026-08-28'))->create(['collection' => 'other']);
        View::factory()->for($this->post, 'viewable')->fromVisitor('four')->viewedAt(Carbon::parse('2026-08-28'))->create();

        expect(views($this->post)->period(Period::pastDays(7))->unique()->viewedBy($user)->compare()->toArray())
            ->toBe(['current' => 1, 'previous' => 3, 'delta' => -2, 'percent' => -66.7])
            ->and(views($this->post)->period(Period::pastDays(7))->collection('other')->compare()->previous)->toBe(1);
    });

    it('steps a relative period back on the clock of the timezone', function (): void {
        // 12:00 UTC on the 10th is 22:00 in Sydney, where the past day started at 14:00 UTC on the 8th.
        viewedAt($this->post, '2026-09-07 13:59:59', '2026-09-07 14:00:00', '2026-09-08 13:59:59', '2026-09-08 14:00:00');

        $comparison = views($this->post)->period(Period::pastDays(1))->timezone('Australia/Sydney')->compare();

        expect($comparison->toArray())->toBe(['current' => 1, 'previous' => 2, 'delta' => -1, 'percent' => -50.0]);
    });

    it('remembers both counts', function (): void {
        viewedAt($this->post, '2026-09-01 12:00:00', '2026-09-05 12:00:00');

        expect(views($this->post)->period(Period::pastDays(7))->remember(60)->compare()->toArray())
            ->toBe(['current' => 1, 'previous' => 1, 'delta' => 0, 'percent' => 0.0]);

        viewedAt($this->post, '2026-09-01 12:00:00', '2026-09-05 12:00:00');

        expect(views($this->post)->period(Period::pastDays(7))->remember(60)->compare()->toArray())
            ->toBe(['current' => 1, 'previous' => 1, 'delta' => 0, 'percent' => 0.0])
            ->and(views($this->post)->period(Period::pastDays(7))->compare()->toArray())
            ->toBe(['current' => 2, 'previous' => 2, 'delta' => 0, 'percent' => 0.0]);
    });

    it('compares every viewable of a type', function (): void {
        viewedAt(Post::factory()->create(), '2026-09-05 12:00:00');
        viewedAt($this->post, '2026-09-05 12:00:00');

        expect(views(Post::class)->period(Period::pastDays(7))->compare()->toArray())
            ->toBe(['current' => 2, 'previous' => 0, 'delta' => 2, 'percent' => null]);
    });

    it('requires a period', function (): void {
        expect(fn (): ViewComparison => views($this->post)->compare())
            ->toThrow(InvalidPeriod::class, 'Comparing needs a period.');
    });

    it('requires a period with a width', function (): void {
        expect(fn (): ViewComparison => views($this->post)->period(Period::since('2026-09-01'))->compare())
            ->toThrow(InvalidPeriod::class, 'has no previous period');
    });
});

describe('counting a set', function (): void {
    beforeEach(function (): void {
        $this->other = Post::factory()->create();
        $this->unviewed = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('visitor_two')->inCollection('custom')->create();
        View::factory()->for($this->other, 'viewable')->fromVisitor('visitor_one')->create();
    });

    function viewsOf(iterable $viewables): Views
    {
        return Container::getInstance()->make(Views::class)->forViewables($viewables);
    }

    it('counts every viewable in one query, in the order given', function (): void {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $counts = viewsOf([$this->unviewed, $this->post, $this->other])->counts();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($counts->all())->toBe([
            $this->unviewed->getKey() => 0,
            $this->post->getKey() => 3,
            $this->other->getKey() => 1,
        ])->and($queries)->toBe(1);
    });

    it('applies the period, collection and unique views', function (): void {
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::now()->subYear())->create();

        expect(viewsOf([$this->post, $this->other])->period(Period::pastDays(7))->counts()->all())
            ->toBe([$this->post->getKey() => 3, $this->other->getKey() => 1])
            ->and(viewsOf([$this->post, $this->other])->collection('custom')->counts()->all())
            ->toBe([$this->post->getKey() => 1, $this->other->getKey() => 0])
            ->and(viewsOf([$this->post, $this->other])->unique()->counts()->all())
            ->toBe([$this->post->getKey() => 3, $this->other->getKey() => 1]);
    });

    it('counts a page of models', function (): void {
        $page = Post::query()->whereKey([$this->post->getKey(), $this->other->getKey()])->orderBy('id')->paginate(2);

        expect(viewsOf($page)->counts()->all())->toBe([$this->post->getKey() => 3, $this->other->getKey() => 1]);
    });

    it('counts a viewable given twice once', function (): void {
        expect(viewsOf([$this->post, Post::query()->find($this->post->getKey())])->counts()->all())->toBe([$this->post->getKey() => 3]);
    });

    it('returns nothing for no viewables without a query', function (): void {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $counts = viewsOf([])->counts();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($counts->all())->toBeEmpty()
            ->and($queries)->toBe(0);
    });

    it('shares the remembered count with count()', function (): void {
        expect(views($this->post)->remember(60)->count())->toBe(3);

        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for($this->other, 'viewable')->create();

        expect(viewsOf([$this->post, $this->other])->remember(60)->counts()->all())
            ->toBe([$this->post->getKey() => 3, $this->other->getKey() => 2])
            ->and(views($this->other)->remember(60)->count())->toBe(2)
            ->and(viewsOf([$this->post, $this->other])->counts()->all())
            ->toBe([$this->post->getKey() => 4, $this->other->getKey() => 2]);
    });

    it('needs the viewables first', function (): void {
        expect(fn (): mixed => $this->app->make(Views::class)->counts())
            ->toThrow(InvalidViewable::class, 'No viewables were given. Call forViewables() before counting them.')
            ->and(fn (): mixed => viewsOf([$this->post])->forViewable($this->post)->counts())
            ->toThrow(InvalidViewable::class, 'No viewables were given.')
            ->and(fn (): mixed => views($this->post)->forViewables([$this->post])->count())
            ->toThrow(InvalidViewable::class, 'No viewable was given.');
    });

    it('refuses viewables of more than one type', function (): void {
        expect(fn (): Views => viewsOf([$this->post, Apartment::factory()->create()]))
            ->toThrow(InvalidViewable::class, 'Every viewable in a set must be of one type');
    });

    it('refuses a viewable that was not saved', function (): void {
        expect(fn (): Views => viewsOf([$this->post, new Post]))
            ->toThrow(InvalidViewable::class, 'Every viewable in a set needs a key, an unsaved ['.Post::class.'] was given.');
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

            public function countByCollection(Viewable $viewable, ViewsQuery $query): array
            {
                return ['sidebar' => 42];
            }

            public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
            {
                return array_fill_keys($keys, 7);
            }

            public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
            {
                return [['type' => Post::class, 'id' => Post::query()->min('id'), 'count' => 99]];
            }
        });

        $series = views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->countByInterval(Granularity::Day);

        expect(views($this->post)->count())->toBe(7)
            ->and(counts($series))->toBe([42, 0])
            ->and(ViewsFacade::top()->entries->first()->count)->toBe(99)
            ->and($this->app->make(Views::class)->forViewables([$this->post])->counts()->all())->toBe([$this->post->getKey() => 7]);
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

describe('counting by collection', function (): void {
    it('counts per collection, most viewed first, with the default collection as an empty string', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->create();
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->count(3)->create();
        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->inCollection('feed')->create();

        expect(views($this->post)->countByCollection())->toBe(['sidebar' => 3, '' => 2, 'feed' => 1]);
    });

    it('counts per collection over a whole type', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->inCollection('feed')->create();

        expect(views(Post::class)->countByCollection())->toBe(['feed' => 2]);
    });

    it('returns no counts for a viewable without views', function (): void {
        expect(views($this->post)->countByCollection())->toBeEmpty();
    });

    it('honours unique, period, collection and viewer', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->fromVisitor('visitor_one')->by($user)->viewedAt(Carbon::parse('2026-09-01 10:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->fromVisitor('visitor_one')->viewedAt(Carbon::parse('2026-09-02 10:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->fromVisitor('visitor_two')->viewedAt(Carbon::parse('2026-09-02 10:00:00'))->create();
        View::factory()->for($this->post, 'viewable')->inCollection('feed')->fromVisitor('visitor_three')->viewedAt(Carbon::parse('2026-09-04 10:00:00'))->create();

        expect(views($this->post)->unique()->countByCollection())->toBe(['feed' => 2, 'sidebar' => 1])
            ->and(views($this->post)->period(Period::create('2026-09-01', '2026-09-03'))->countByCollection())->toBe(['sidebar' => 2, 'feed' => 1])
            ->and(views($this->post)->collection('feed')->countByCollection())->toBe(['feed' => 2])
            ->and(views($this->post)->viewedBy($user)->countByCollection())->toBe(['sidebar' => 1]);
    });

    it('sums to the plain count', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->count(3)->create();
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        expect(array_sum(views($this->post)->countByCollection()))->toBe(views($this->post)->count());
    });

    it('remembers the counts', function (): void {
        View::factory()->for($this->post, 'viewable')->inCollection('sidebar')->create();

        expect(views($this->post)->remember(60)->countByCollection())->toBe(['sidebar' => 1]);

        View::factory()->for($this->post, 'viewable')->inCollection('feed')->create();

        expect(views($this->post)->remember(60)->countByCollection())->toBe(['sidebar' => 1])
            ->and(views($this->post)->countByCollection())->toBe(['feed' => 1, 'sidebar' => 1]);
    });

    it('reads through the ViewSource bound in the container', function (): void {
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
                return ['feed' => 1, 'sidebar' => 42];
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

        expect(views($this->post)->countByCollection())->toBe(['sidebar' => 42, 'feed' => 1]);
    });
});

describe('ranking', function (): void {
    /** @return list<array{string, mixed, int, int}> */
    function rankingOf(Ranking $ranking): array
    {
        return $ranking->entries->map(fn (Entry $entry): array => [$entry->viewable::class, $entry->viewable->getKey(), $entry->count, $entry->rank])->all();
    }

    it('ranks the most viewed content of every type', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for($apartment, 'viewable')->count(3)->create();
        View::factory()->for($other, 'viewable')->create();

        $ranking = ViewsFacade::top();

        expect(rankingOf($ranking))->toBe([
            [Apartment::class, $apartment->getKey(), 3, 1],
            [Post::class, $this->post->getKey(), 2, 2],
            [Post::class, $other->getKey(), 1, 3],
        ])
            ->and($ranking->viewables()->first()->is($apartment))->toBeTrue()
            ->and($ranking)->toHaveCount(3);
    });

    it('ranks within a type for a viewable without a key', function (): void {
        $other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for($other, 'viewable')->count(2)->create();
        View::factory()->for(Apartment::factory()->create(), 'viewable')->count(3)->create();

        expect(views(Post::class)->top()->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()])
            ->and(ViewsFacade::forViewable(new Post)->top()->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()]);
    });

    it('refuses to rank a single viewable', function (): void {
        expect(fn (): Ranking => views($this->post)->top())
            ->toThrow(InvalidViewable::class, 'top() ranks every viewable of a type or every type. ['.Post::class.'] with key '.$this->post->getKey().' was given; pass a model without a key, or none at all.');
    });

    it('refuses a limit below one', function (): void {
        expect(fn (): Ranking => ViewsFacade::top(0))
            ->toThrow(InvalidLimit::class, 'top() needs a limit of at least one, 0 given.');
    });

    it('stops at the limit', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();
        View::factory()->for(Post::factory()->create(), 'viewable')->create();

        expect(ViewsFacade::top(1)->viewables()->modelKeys())->toBe([$this->post->getKey()]);
    });

    it('applies the period, collection, viewer and uniqueness', function (): void {
        $other = Post::factory()->create();
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->inCollection('custom')->fromVisitor('one')->viewedAt(Carbon::parse('2026-01-10'))->count(3)->create();
        View::factory()->for($this->post, 'viewable')->fromVisitor('one')->viewedAt(Carbon::parse('2026-02-10'))->create();
        View::factory()->for($other, 'viewable')->inCollection('custom')->fromVisitor('one')->viewedAt(Carbon::parse('2026-02-10'))->by($user)->create();
        View::factory()->for($other, 'viewable')->inCollection('custom')->fromVisitor('two')->viewedAt(Carbon::parse('2026-02-10'))->by($user)->create();

        expect(ViewsFacade::period(Period::since('2026-02-01'))->top()->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()])
            ->and(ViewsFacade::collection('custom')->top()->viewables()->modelKeys())->toBe([$this->post->getKey(), $other->getKey()])
            ->and(ViewsFacade::viewedBy($user)->top()->viewables()->modelKeys())->toBe([$other->getKey()])
            ->and(ViewsFacade::unique()->top()->entries->map(fn (Entry $entry): int => $entry->count)->all())->toBe([2, 1])
            ->and(ViewsFacade::top()->entries->map(fn (Entry $entry): int => $entry->count)->all())->toBe([4, 2]);
    });

    it('anchors a relative period on the clock of the timezone', function (): void {
        Carbon::setTestNow('2026-09-10 12:00:00');
        $other = Post::factory()->create();

        // pastDays(1) starts at yesterday's midnight: 2026-09-09 00:00 UTC, or
        // 2026-09-08 14:00 UTC when anchored on Sydney's clock, which is the
        // only one of the two that reaches back to 20:00 on the 8th.
        View::factory()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2026-09-08 20:00:00'))->create();
        View::factory()->for($other, 'viewable')->viewedAt(Carbon::parse('2026-09-10 01:00:00'))->count(2)->create();

        expect(ViewsFacade::period(Period::pastDays(1))->top()->viewables()->modelKeys())->toBe([$other->getKey()])
            ->and(ViewsFacade::period(Period::pastDays(1))->timezone('Australia/Sydney')->top()->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()]);
    });

    it('leaves out a viewable whose model is gone', function (): void {
        $gone = KeepsViewsPost::create(['title' => 'Title', 'body' => 'Body']);
        View::factory()->for($gone, 'viewable')->count(5)->create();
        View::factory()->for($this->post, 'viewable')->create();
        $gone->delete();

        expect(rankingOf(ViewsFacade::top()))->toBe([[Post::class, $this->post->getKey(), 1, 1]]);
    });

    it('is empty when nothing was viewed', function (): void {
        expect(ViewsFacade::top()->isEmpty())->toBeTrue()
            ->and(ViewsFacade::top()->toArray())->toBe([]);
    });

    it('serializes to JSON', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        expect(ViewsFacade::top()->jsonSerialize())->toBe([
            ['rank' => 1, 'count' => 2, 'score' => null, 'viewable' => $this->post->fresh()->toArray()],
        ]);
    });

    it('can remember the ranking', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        expect(ViewsFacade::remember(60)->top()->viewables()->modelKeys())->toBe([$this->post->getKey()]);

        $other = Post::factory()->create();
        View::factory()->for($other, 'viewable')->count(5)->create();

        expect(ViewsFacade::remember(60)->top()->viewables()->modelKeys())->toBe([$this->post->getKey()])
            ->and(ViewsFacade::top()->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()])
            ->and(ViewsFacade::remember(60)->top(5)->viewables()->modelKeys())->toBe([$other->getKey(), $this->post->getKey()]);
    });

    it('loads the models afresh when the ranking is remembered', function (): void {
        View::factory()->for($this->post, 'viewable')->count(2)->create();

        expect(ViewsFacade::remember(60)->top()->viewables()->first()->title)->toBe($this->post->title);

        $this->post->update(['title' => 'Renamed']);

        expect(ViewsFacade::remember(60)->top()->viewables()->first()->title)->toBe('Renamed');
    });
});

describe('also viewed', function (): void {
    beforeEach(function (): void {
        Config::set('eloquent-viewable.querying.also_viewed.minimum_visitors', 1);
    });

    /** @param  list<string>  $visitors */
    function viewedByEach(Model $viewable, array $visitors, string $viewedAt = '2026-01-10'): void
    {
        foreach ($visitors as $visitor) {
            View::factory()->for($viewable, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse($viewedAt))->create();
        }
    }

    it('ranks what the visitors of a model also viewed', function (): void {
        $apartment = Apartment::factory()->create();
        $other = Post::factory()->create();

        viewedByEach($this->post, ['one', 'two']);
        viewedByEach($apartment, ['one', 'two', 'stranger']);
        viewedByEach($other, ['two']);

        expect(rankingOf(views($this->post)->alsoViewed()))->toBe([
            [Apartment::class, $apartment->getKey(), 2, 1],
            [Post::class, $other->getKey(), 1, 2],
        ])
            ->and(views($this->post)->alsoViewed(1)->viewables()->modelKeys())->toBe([$apartment->getKey()])
            ->and(views($this->post)->alsoViewed(among: Post::class)->viewables()->modelKeys())->toBe([$other->getKey()]);
    });

    it('leaves out what fewer visitors than the minimum viewed', function (): void {
        Config::set('eloquent-viewable.querying.also_viewed.minimum_visitors', 2);
        $popular = Post::factory()->create();
        $rare = Post::factory()->create();

        viewedByEach($this->post, ['one', 'two']);
        viewedByEach($popular, ['one', 'two']);
        viewedByEach($rare, ['one']);

        expect(views($this->post)->alsoViewed()->viewables()->modelKeys())->toBe([$popular->getKey()]);
    });

    it('needs three visitors in common out of the box', function (): void {
        Config::set('eloquent-viewable.querying.also_viewed.minimum_visitors', 3);
        $other = Post::factory()->create();

        viewedByEach($this->post, ['one', 'two', 'three']);
        viewedByEach($other, ['one', 'two']);

        expect(views($this->post)->alsoViewed()->isEmpty())->toBeTrue();

        viewedByEach($other, ['three']);

        expect(views($this->post)->alsoViewed()->viewables()->modelKeys())->toBe([$other->getKey()]);
    });

    it('reads only the most recent visitors of the model', function (string|int|null $maxVisitors, int $count): void {
        Config::set('eloquent-viewable.querying.also_viewed.max_visitors', $maxVisitors);
        $other = Post::factory()->create();

        viewedByEach($this->post, ['early'], '2026-01-01');
        viewedByEach($this->post, ['late'], '2026-01-20');
        viewedByEach($other, ['early', 'late']);

        expect(views($this->post)->alsoViewed()->entries->first()->count)->toBe($count);
    })->with([
        'one' => [1, 1],
        'one, as a string' => ['1', 1],
        'every visitor' => [null, 2],
    ]);

    it('refuses a max_visitors that is not a positive integer or null', function (mixed $value): void {
        Config::set('eloquent-viewable.querying.also_viewed.max_visitors', $value);

        views($this->post)->alsoViewed();
    })->with([0, 'many', 1.5])->throws(InvalidConfiguration::class, 'The `eloquent-viewable.querying.also_viewed.max_visitors` config value must be a positive integer or null');

    it('applies the period and collection', function (): void {
        $other = Post::factory()->create();

        viewedByEach($this->post, ['january'], '2026-01-10');
        viewedByEach($this->post, ['february'], '2026-02-10');
        viewedByEach($other, ['january', 'february'], '2026-02-10');

        expect(views($this->post)->alsoViewed()->entries->first()->count)->toBe(2)
            ->and(views($this->post)->period(Period::since('2026-02-01'))->alsoViewed()->entries->first()->count)->toBe(1)
            ->and(views($this->post)->collection('sidebar')->alsoViewed()->isEmpty())->toBeTrue();
    });

    it('refuses a model without a key', function (): void {
        expect(fn (): Ranking => views(Post::class)->alsoViewed())
            ->toThrow(InvalidViewable::class, 'alsoViewed() ranks what the visitors of one viewable also viewed. A ['.Post::class.'] without a key was given; pass a saved model.');
    });

    it('refuses a limit below one', function (): void {
        expect(fn (): Ranking => views($this->post)->alsoViewed(0))
            ->toThrow(InvalidLimit::class, 'alsoViewed() needs a limit of at least one, 0 given.');
    });

    it('refuses to narrow to one viewer', function (): void {
        expect(fn (): Ranking => views($this->post)->viewedBy(User::factory()->create())->alsoViewed())
            ->toThrow(InvalidViewer::class, 'alsoViewed() ranks across every visitor, so it cannot be narrowed to one viewer.');
    });

    it('refuses to rank among a class that is not viewable', function (): void {
        expect(fn (): Ranking => views($this->post)->alsoViewed(among: User::class))
            ->toThrow(InvalidViewable::class, 'Class ['.User::class.'] must implement');
    });

    it('can remember the ranking until the cache of the model is forgotten', function (): void {
        $other = Post::factory()->create();
        $later = Post::factory()->create();

        viewedByEach($this->post, ['one']);
        viewedByEach($other, ['one']);

        expect(views($this->post)->remember(60)->alsoViewed()->viewables()->modelKeys())->toBe([$other->getKey()]);

        viewedByEach($later, ['one', 'one']);

        expect(views($this->post)->remember(60)->alsoViewed()->viewables()->modelKeys())->toBe([$other->getKey()])
            ->and(views($this->post)->remember(60)->alsoViewed(among: Post::class)->viewables()->modelKeys())->toBe([$other->getKey(), $later->getKey()]);

        views($this->post)->forgetCache();

        expect(views($this->post)->remember(60)->alsoViewed()->viewables()->modelKeys())->toBe([$other->getKey(), $later->getKey()]);
    });

    it('refuses a source that cannot rank it', function (bool $remember): void {
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

        views($this->post)->remember($remember ? 60 : null)->alsoViewed();
    })->with(['read' => false, 'remembered' => true])->throws(UnsupportedBySource::class, 'cannot rank what visitors also viewed, so alsoViewed() cannot read from it.');
});

describe('returning visitors', function (): void {
    beforeEach(function (): void {
        Carbon::setTestNow('2026-09-10 12:00:00');
    });

    function visitedBy(Viewable $viewable, string $visitor, string ...$dateTimes): void
    {
        foreach ($dateTimes as $dateTime) {
            View::factory()->for($viewable, 'viewable')->fromVisitor($visitor)->viewedAt(Carbon::parse($dateTime))->create();
        }
    }

    it('counts the visitors who viewed on two days or more', function (): void {
        visitedBy($this->post, 'one', '2026-09-01 10:00:00', '2026-09-01 11:00:00');
        visitedBy($this->post, 'two', '2026-09-01 10:00:00', '2026-09-04 10:00:00');
        visitedBy($this->post, 'three', '2026-09-02 10:00:00', '2026-09-05 10:00:00', '2026-09-08 10:00:00');

        expect(views($this->post)->returning()->count())->toBe(2)
            ->and(views($this->post)->returning(false)->count())->toBe(7)
            ->and(views($this->post)->period(Period::since('2026-09-03'))->returning()->count())->toBe(1);
    });

    it('counts visitors whether or not unique() is called', function (): void {
        visitedBy($this->post, 'one', '2026-09-01 10:00:00', '2026-09-02 10:00:00', '2026-09-02 11:00:00');

        expect(views($this->post)->unique()->returning()->count())->toBe(1)
            ->and(views($this->post)->returning()->count())->toBe(1);
    });

    it('tells whether one viewer came back', function (): void {
        $user = User::factory()->create();

        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('one')->viewedAt(Carbon::parse('2026-09-01'))->create();
        View::factory()->for($this->post, 'viewable')->by($user)->fromVisitor('one')->viewedAt(Carbon::parse('2026-09-03'))->create();
        visitedBy($this->post, 'two', '2026-09-01 10:00:00', '2026-09-04 10:00:00');

        expect(views($this->post)->viewedBy($user)->returning()->count())->toBe(1)
            ->and(views($this->post)->viewedBy(User::factory()->create())->returning()->count())->toBe(0);
    });

    it('compares the visitors who came back with the period before', function (): void {
        visitedBy($this->post, 'one', '2026-08-28 10:00:00', '2026-08-29 10:00:00');
        visitedBy($this->post, 'two', '2026-09-04 10:00:00', '2026-09-05 10:00:00');
        visitedBy($this->post, 'three', '2026-09-06 10:00:00', '2026-09-08 10:00:00');
        visitedBy($this->post, 'four', '2026-09-07 10:00:00');

        expect(views($this->post)->period(Period::pastDays(7))->returning()->compare()->toArray())
            ->toBe(['current' => 2, 'previous' => 1, 'delta' => 1, 'percent' => 100.0]);
    });

    it('counts the visitors per number of days', function (): void {
        visitedBy($this->post, 'one', '2026-09-01 10:00:00');
        visitedBy($this->post, 'two', '2026-09-01 10:00:00', '2026-09-01 11:00:00');
        visitedBy($this->post, 'three', '2026-09-01 10:00:00', '2026-09-02 10:00:00');
        visitedBy($this->post, 'four', '2026-09-01 10:00:00', '2026-09-02 10:00:00', '2026-09-03 10:00:00', '2026-09-04 10:00:00');

        $frequency = views($this->post)->countByFrequency();

        expect($frequency)->toBeInstanceOf(VisitFrequency::class)
            ->and($frequency->toArray())->toBe([1 => 2, 2 => 1, '3+' => 1])
            ->and($frequency->new())->toBe(2)
            ->and($frequency->returning())->toBe(2)
            ->and($frequency->returningShare())->toBe(0.5)
            ->and(views($this->post)->countByFrequency(5)->toArray())->toBe([1 => 2, 2 => 1, 3 => 0, 4 => 1, '5+' => 0]);
    });

    it('counts the days on the clock of the timezone', function (): void {
        visitedBy($this->post, 'one', '2026-09-01 22:00:00', '2026-09-02 01:00:00');

        $period = Period::create('2026-09-01', '2026-09-03');

        expect(views($this->post)->period($period)->returning()->count())->toBe(1)
            ->and(views($this->post)->period($period)->timezone('Europe/Amsterdam')->returning()->count())->toBe(0);
    });

    it('refuses a cap below two', function (): void {
        expect(fn (): VisitFrequency => views($this->post)->countByFrequency(1))
            ->toThrow(InvalidFrequency::class, 'countByFrequency() needs a cap of at least two, so new and returning visitors stay apart. 1 given.');
    });

    it('remembers the days per visitor until the lifetime', function (): void {
        visitedBy($this->post, 'one', '2026-09-01 10:00:00', '2026-09-02 10:00:00');

        expect(views($this->post)->remember(60)->returning()->count())->toBe(1);

        visitedBy($this->post, 'two', '2026-09-01 10:00:00', '2026-09-02 10:00:00');

        expect(views($this->post)->remember(60)->returning()->count())->toBe(1)
            ->and(views($this->post)->remember(60)->countByFrequency()->returning())->toBe(1)
            ->and(views($this->post)->returning()->count())->toBe(2);
    });

    it('refuses to read returning visitors where only count() and compare() can', function (string $method, Closure $read): void {
        expect(fn (): mixed => $read(views($this->post)->returning()))
            ->toThrow(InvalidReturning::class, "returning() narrows count() and compare() to visitors who came back on another day, so {$method} cannot read it.");
    })->with([
        'counts' => ['counts()', fn (Views $views): mixed => $views->forViewables(Post::all())->counts()],
        'countByInterval' => ['countByInterval()', fn (Views $views): mixed => $views->countByInterval(Granularity::Day)],
        'countByCollection' => ['countByCollection()', fn (Views $views): mixed => $views->countByCollection()],
        'countByDimension' => ['countByDimension()', fn (Views $views): mixed => $views->countByDimension()],
        'top' => ['top()', fn (Views $views): mixed => $views->top()],
        'trending' => ['trending()', fn (Views $views): mixed => $views->trending()],
        'alsoViewed' => ['alsoViewed()', fn (Views $views): mixed => $views->alsoViewed()],
    ]);

    it('refuses a source that cannot count the days per visitor', function (bool $remember): void {
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

        views($this->post)->remember($remember ? 60 : null)->returning()->count();
    })->with(['read' => false, 'remembered' => true])->throws(UnsupportedBySource::class, 'cannot count how often visitors came back, so returning() and countByFrequency() cannot read from it.');
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

describe('remembering in redis', function (): void {
    beforeEach(function (): void {
        // The database after the stream's, so flushing it leaves the stream tests alone.
        $default = Config::get('database.redis.default');
        Config::set('database.redis.views-cache', [...$default, 'database' => $default['database'] + 1]);
        Config::set('cache.stores.views-redis', ['driver' => 'redis', 'connection' => 'views-cache']);
        Config::set('eloquent-viewable.querying.cache.store', 'views-redis');

        View::factory()->for($this->post, 'viewable')->count(3)->create();
    });

    afterEach(function (): void {
        Cache::store('views-redis')->flush();
    });

    it('reads a remembered count back as an integer', function (): void {
        expect(views($this->post)->remember(60)->count())->toBe(3);

        View::factory()->for($this->post, 'viewable')->create();

        expect(views($this->post)->remember(60)->count())->toBe(3);
    });

    it('reads the remembered counts of a set back', function (): void {
        expect(viewsOf([$this->post])->remember(60)->counts()->all())->toBe([$this->post->getKey() => 3]);

        View::factory()->for($this->post, 'viewable')->create();

        expect(viewsOf([$this->post])->remember(60)->counts()->all())->toBe([$this->post->getKey() => 3]);
    });
});

describe('forgetting remembered counts', function (): void {
    beforeEach(function (): void {
        $this->other = Post::factory()->create();

        View::factory()->for($this->post, 'viewable')->count(3)->create();
        View::factory()->for($this->other, 'viewable')->count(2)->create();

        expect(views($this->post)->remember(60)->count())->toBe(3)
            ->and(views($this->other)->remember(60)->count())->toBe(2)
            ->and(views(Post::class)->remember(60)->count())->toBe(5)
            ->and(ViewsFacade::remember(60)->top()->entries->first()?->count)->toBe(3);

        View::factory()->for($this->post, 'viewable')->create();
        View::factory()->for($this->other, 'viewable')->create();
    });

    it('forgets the counts of a model, the total of its type and the ranking', function (): void {
        views($this->post)->forgetCache();

        expect(views($this->post)->remember(60)->count())->toBe(4)
            ->and(views($this->other)->remember(60)->count())->toBe(2)
            ->and(views(Post::class)->remember(60)->count())->toBe(7)
            ->and(ViewsFacade::remember(60)->top()->entries->first()?->count)->toBe(4);
    });

    it('forgets every model of a type', function (): void {
        views(Post::class)->forgetCache();

        expect(views($this->post)->remember(60)->count())->toBe(4)
            ->and(views($this->other)->remember(60)->count())->toBe(3)
            ->and(views(Post::class)->remember(60)->count())->toBe(7);
    });

    it('forgets every remembered count on a flush', function (): void {
        ViewsFacade::flushCache();

        expect(views($this->post)->remember(60)->count())->toBe(4)
            ->and(views($this->other)->remember(60)->count())->toBe(3)
            ->and(views(Post::class)->remember(60)->count())->toBe(7)
            ->and(ViewsFacade::remember(60)->top()->entries->first()?->count)->toBe(4);
    });

    it('forgets the remembered counts of destroyed views', function (): void {
        views($this->post)->destroy();

        expect(views($this->post)->remember(60)->count())->toBe(0)
            ->and(views($this->other)->remember(60)->count())->toBe(2)
            ->and(views(Post::class)->remember(60)->count())->toBe(3);
    });

    it('forgets the remembered counts of a model deleted with its views', function (): void {
        $this->post->delete();

        expect(views(Post::class)->remember(60)->count())->toBe(3);
    });

    it('announces destroyed views', function (): void {
        Event::fake([ViewsDestroyed::class]);

        views($this->post)->destroy();

        Event::assertDispatched(ViewsDestroyed::class, fn (ViewsDestroyed $event): bool => $event->viewable === $this->post);
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
