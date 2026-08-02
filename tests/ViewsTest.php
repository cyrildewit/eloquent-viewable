<?php

declare(strict_types=1);

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestClasses\TestVisitor;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitor;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    $this->post = Post::factory()->create();
});

it('is macroable', function (): void {
    Views::macro('newMethod', fn (): string => 'someValue');

    expect(Container::getInstance()->make(Views::class)->newMethod())->toBe('someValue');
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
            ->record())->toThrow(Exception::class);
    });

    it('returns true when a view is recorded', function (): void {
        expect(views($this->post)->record())->toBeTrue();
    });

    it('returns false when a view is not recorded', function (): void {
        views($this->post)->cooldown(Carbon::now()->addMinutes(10))->record();

        expect(views($this->post)->cooldown(Carbon::now()->addMinutes(10))->record())->toBeFalse();
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

    it('does not record views if session delay is active with collection', function (): void {
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
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_two']);

        expect($this->post)->toHaveUniqueViewsCount(2);
    });

    it('can count the views of a period', function (): void {
        Carbon::setTestNow(Carbon::now());

        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-15')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-15')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-15')]);

        expect(views($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count())->toBe(6)
            ->and(views($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count())->toBe(4)
            ->and(views($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count())->toBe(4);
    });

    it('can remove the period', function (): void {
        Carbon::setTestNow(Carbon::now());

        TestHelper::createView($this->post);
        TestHelper::createView($this->post);

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

        TestHelper::createView($postOne);
        TestHelper::createView($postTwo);
        TestHelper::createView($postTwo);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        expect(new Post)->toHaveViewsCount(3);
    });

    it('can count the unique views by type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_one']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_three']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_one']);

        expect(new Post)->toHaveUniqueViewsCount(2);
    });
});

describe('destroying', function (): void {
    it('can destroy the views', function (): void {
        $post = $this->post;
        $apartment = Apartment::factory()->create();

        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        views($post)->destroy();

        expect($post)->toHaveViewsCount(0);
    });

    it('can destroy the views of a viewable type', function (): void {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        TestHelper::createView($postOne);
        TestHelper::createView($postOne);
        TestHelper::createView($postOne);
        TestHelper::createView($postTwo);
        TestHelper::createView($postTwo);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

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
});

describe('visitor handling', function (): void {
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
                Container::getInstance()->make(TestVisitor::class)
            )
            ->record();

        views($this->post)->record();

        expect(View::count())->toBe(2);
    });
});
