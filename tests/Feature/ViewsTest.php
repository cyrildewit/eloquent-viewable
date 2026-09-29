<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Events\ViewRecorded;
use CyrildeWit\EloquentViewable\Exceptions\ViewRecordException;
use CyrildeWit\EloquentViewable\Jobs\StoreView;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Factories\ViewFactory;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestClasses\TestVisitor;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is macroable', function (): void {
    Views::macro('newMethod', fn (): string => 'someValue');

    expect($this->app->make(Views::class)->newMethod())->toBe('someValue');
});

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
            ->record())->toThrow(ViewRecordException::class);
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
});

describe('queueing', function (): void {
    it('does not queue the view by default', function (): void {
        Bus::fake();

        views($this->post)->record();

        Bus::assertNotDispatched(StoreView::class);
    });

    it('queues the view when queue() is used', function (): void {
        Bus::fake();

        $result = views($this->post)->queue()->record();

        expect($result)->toBeTrue();

        Bus::assertDispatched(StoreView::class);
    });

    it('queues the view when enabled in the config', function (): void {
        Config::set('eloquent-viewable.queue.enabled', true);

        Bus::fake();

        views($this->post)->record();

        Bus::assertDispatched(StoreView::class);
    });

    it('can force synchronous recording when queueing is enabled in the config', function (): void {
        Config::set('eloquent-viewable.queue.enabled', true);

        Bus::fake();

        views($this->post)->queue(false)->record();

        Bus::assertNotDispatched(StoreView::class);

        expect(View::count())->toBe(1);
    });

    it('dispatches on the configured connection and queue', function (): void {
        Config::set('eloquent-viewable.queue.connection', 'redis');
        Config::set('eloquent-viewable.queue.queue', 'views');

        Bus::fake();

        views($this->post)->queue()->record();

        Bus::assertDispatched(StoreView::class, fn (StoreView $job): bool => $job->connection === 'redis' && $job->queue === 'views');
    });

    it('stores the view when the queued job is processed', function (): void {
        views($this->post)->queue()->collection('custom')->record();

        $view = View::sole();

        expect($view->viewable_id)->toBe($this->post->getKey())
            ->and($view->viewable_type)->toBe($this->post->getMorphClass())
            ->and($view->collection)->toBe('custom');
    });

    it('does not queue bot views', function (): void {
        $this->app->bind(CrawlerDetector::class, fn (): CrawlerDetector => new class implements CrawlerDetector
        {
            public function isCrawler(): bool
            {
                return true;
            }
        });

        Bus::fake();

        expect(views($this->post)->queue()->record())->toBeFalse();

        Bus::assertNotDispatched(StoreView::class);
    });

    it('does not queue views from visitors with the dnt header', function (): void {
        Config::set('eloquent-viewable.honor_dnt', true);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        Bus::fake();

        expect(views($this->post)->queue()->record())->toBeFalse();

        Bus::assertNotDispatched(StoreView::class);
    });

    it('does not queue views from ignored ip addresses', function (): void {
        Config::set('eloquent-viewable.ignored_ip_addresses', ['127.20.22.6']);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('ip')->andReturn('127.20.22.6');
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        Bus::fake();

        expect(views($this->post)->queue()->record())->toBeFalse();

        Bus::assertNotDispatched(StoreView::class);
    });

    it('does not queue views that are on cooldown', function (): void {
        Bus::fake();

        views($this->post)->queue()->cooldown(10)->record();

        expect(views($this->post)->queue()->cooldown(10)->record())->toBeFalse();

        Bus::assertDispatchedTimes(StoreView::class, 1);
    });
});

describe('cooldowns', function (): void {
    it('does not record views if cooldown is active', function (): void {
        views($this->post)
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        views($this->post)
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        expect(View::count())->toBe(1);
    });

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
        ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_one')->count(2)->create();
        ViewFactory::new()->for($this->post, 'viewable')->fromVisitor('visitor_two')->create();

        expect($this->post)->toHaveUniqueViewsCount(2);
    });

    it('can count the views of a period', function (): void {
        $this->freezeTime();

        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-01-10'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-01-15'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-02-10'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-02-15'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-03-10'))->create();
        ViewFactory::new()->for($this->post, 'viewable')->viewedAt(Carbon::parse('2018-03-15'))->create();

        expect(views($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count())->toBe(6)
            ->and(views($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count())->toBe(4)
            ->and(views($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count())->toBe(4);
    });

    it('can remove the period', function (): void {
        $this->freezeTime();

        ViewFactory::new()->for($this->post, 'viewable')->count(2)->create();

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

        ViewFactory::new()->for($postOne, 'viewable')->create();
        ViewFactory::new()->for($postTwo, 'viewable')->count(2)->create();
        ViewFactory::new()->for($apartment, 'viewable')->count(2)->create();

        expect(new Post)->toHaveViewsCount(3);
    });

    it('can count the unique views by type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        ViewFactory::new()->for($postOne, 'viewable')->fromVisitor('visitor_one')->create();
        ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_two')->create();
        ViewFactory::new()->for($postTwo, 'viewable')->fromVisitor('visitor_one')->create();
        ViewFactory::new()->for($apartment, 'viewable')->fromVisitor('visitor_three')->create();
        ViewFactory::new()->for($apartment, 'viewable')->fromVisitor('visitor_one')->create();

        expect(new Post)->toHaveUniqueViewsCount(2);
    });
});

describe('destroying', function (): void {
    it('can destroy the views', function (): void {
        $post = $this->post;
        $apartment = Apartment::factory()->create();

        ViewFactory::new()->for($post, 'viewable')->count(4)->create();
        ViewFactory::new()->for($apartment, 'viewable')->count(2)->create();

        views($post)->destroy();

        expect($post)->toHaveViewsCount(0);
    });

    it('can destroy the views of a viewable type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        ViewFactory::new()->for($postOne, 'viewable')->count(3)->create();
        ViewFactory::new()->for($postTwo, 'viewable')->count(2)->create();
        ViewFactory::new()->for($apartment, 'viewable')->count(2)->create();

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
        Config::set('eloquent-viewable.cache.store', 'views');

        ViewFactory::new()->for($this->post, 'viewable')->count(3)->create();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        ViewFactory::new()->for($this->post, 'viewable')->count(2)->create();

        // Flushing the default store must not touch the remembered count.
        Cache::flush();

        expect(views($this->post)->remember(60)->count())->toBe(3);

        Cache::store('views')->flush();

        expect(views($this->post)->remember(60)->count())->toBe(5);
    });
});

describe('visitor handling', function (): void {
    it('does not record views from a crawler user agent', function (string $userAgent, bool $recorded): void {
        $this->app['request']->server->set('HTTP_USER_AGENT', $userAgent);

        expect(views($this->post)->record())->toBe($recorded)
            ->and(View::count())->toBe($recorded ? 1 : 0);
    })->with([
        'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', false],
        'Chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', true],
    ]);

    it('does not record bot views', function (): void {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, fn (): CrawlerDetector => new class implements CrawlerDetector
        {
            public function isCrawler(): bool
            {
                return true;
            }
        });

        views($this->post)->record();
        views($this->post)->record();

        expect(View::count())->toBe(0);
    });

    it('does not record views from visitors with dnt header', function (): void {
        Config::set('eloquent-viewable.honor_dnt', true);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        expect(View::count())->toBe(0);
    });

    it('does not record views from ignored ip addresses', function (): void {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        $this->mock(Visitor::class, function ($mock): void {
            $mock->shouldReceive('ip')->andReturn('127.20.22.6');
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        views($this->post)->record();
        views($this->post)->record();

        expect(View::count())->toBe(0);
    });

    it('can set the visitor instance', function (): void {
        views($this->post)->record();

        views($this->post)
            ->useVisitor(
                $this->app->make(TestVisitor::class)
            )
            ->record();

        views($this->post)->record();

        expect(View::count())->toBe(2);
    });
});
