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

it('can record a view', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(View::count())->toBe(1);
});

it('can record multiple views', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(View::count())->toBe(3);
});

it('throws an exception when recording a view for a viewable type', function (): void {
    expect(fn () => Container::getInstance()->make(Views::class)
        ->forViewable(new Post)
        ->cooldown(Carbon::now()->addMinutes(10))
        ->record())->toThrow(Exception::class);
});

it('does not record views if cooldown is active', function (): void {
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->cooldown(Carbon::now()->addMinutes(10))
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
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
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->cooldown(10)
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->cooldown(10)
        ->record();

    expect(View::count())->toBe(1);
});

it('does not record views if cooldown is active with collection', function (): void {
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->collection('test')
        ->cooldown(Carbon::now()->addMinutes(10))
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->collection('test')
        ->cooldown(Carbon::now()->addMinutes(10))
        ->record();

    expect(View::count())->toBe(1);
});

it('can remove a cooldown', function (): void {
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->cooldown(null)
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->cooldown(null)
        ->record();

    expect(View::count())->toBe(2);
});

it('can record a view under a collection', function (): void {
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->collection('customCollection')
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->record();

    expect(View::where('collection', 'customCollection')->count())->toBe(1);
});

it('can remove the collection', function (): void {
    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->collection(null)
        ->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->record();

    expect(View::where('collection', null)->count())->toBe(2);
});

it('can count the views', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->count())->toBe(3);
});

it('can count the unique views', function (): void {
    TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
    TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
    TestHelper::createView($this->post, ['visitor' => 'visitor_two']);

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->unique()->count())->toBe(2);
});

it('can count the views of a period', function (): void {
    Carbon::setTestNow(Carbon::now());

    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-10')]);
    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-15')]);
    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-10')]);
    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-15')]);
    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-10')]);
    TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-15')]);

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count())->toBe(6)
        ->and(Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count())->toBe(4)
        ->and(Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count())->toBe(4);
});

it('can remove the period', function (): void {
    Carbon::setTestNow(Carbon::now());

    TestHelper::createView($this->post);
    TestHelper::createView($this->post);

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->period(null)->count())->toBe(2);
});

it('can count the views with a collection', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->count())->toBe(2)
        ->and(Container::getInstance()->make(Views::class)->forViewable($this->post)->count())->toBe(3);
});

it('can destroy the views', function (): void {
    $post = $this->post;
    $apartment = Apartment::factory()->create();

    TestHelper::createView($post);
    TestHelper::createView($post);
    TestHelper::createView($post);
    TestHelper::createView($post);
    TestHelper::createView($apartment);
    TestHelper::createView($apartment);

    Container::getInstance()->make(Views::class)->forViewable($post)->destroy();

    expect(Container::getInstance()->make(Views::class)->forViewable($post)->count())->toBe(0);
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

    Container::getInstance()->make(Views::class)->forViewable(new Post)->destroy();

    expect(Container::getInstance()->make(Views::class)->forViewable(new Post)->count())->toBe(0);
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

    expect(Container::getInstance()->make(Views::class)->forViewable(new Post)->count())->toBe(3);
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

    expect(Container::getInstance()->make(Views::class)->forViewable(new Post)->unique()->count())->toBe(2)
        ->toBe(2);
});

it('can remember the views counts', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count())->toBe(3);
});

it('can remove the remember lifetime', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->remember()->count())->toBe(5);
});

it('can remember the views counts with custom lifetime as integers', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(10)->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(10)->count())->toBe(3);
});

it('can remember the views counts with custom lifetime as date time interface', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(new DateTime('2050-01-01'))->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(new DateTime('2050-01-01'))->count())->toBe(3);
});

it('can remember the views counts with custom lifetime as carbon interface', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(Carbon::now()->addHours(2))->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(Carbon::now()->addHours(2))->count())->toBe(3);
});

it('throws an exception when remember lifetime is of incorrect type', function (): void {
    expect(fn () => Container::getInstance()->make(Views::class)->forViewable($this->post)->remember('not good')->count())
        ->toThrow(TypeError::class);
});

it('can remember the views counts of a type', function (): void {
    $postOne = $this->post;
    $postTwo = Post::factory()->create();
    $apartment = Apartment::factory()->create();

    Container::getInstance()->make(Views::class)->forViewable($postOne)->record();
    Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
    Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
    Container::getInstance()->make(Views::class)->forViewable($apartment)->record();
    Container::getInstance()->make(Views::class)->forViewable($apartment)->record();

    expect(views(Post::class)->remember(60)->count())->toBe(3);

    Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
    Container::getInstance()->make(Views::class)->forViewable($apartment)->record();

    expect(views(Post::class)->remember(60)->count())->toBe(3);
});

it('does not record bot views', function (): void {
    // Faking that the visitor is a bot
    $this->app->bind(CrawlerDetector::class, fn (): CrawlerDetector => new class implements CrawlerDetector
    {
        public function isCrawler(): bool
        {
            return true;
        }
    });

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(View::count())->toBe(0);
});

it('does not record views from visitors with dnt header', function (): void {
    Config::set('eloquent-viewable.honor_dnt', true);

    $this->mock(Visitor::class, function ($mock): void {
        $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
        $mock->shouldReceive('isCrawler')->andReturn(false);
    });

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

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

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(View::count())->toBe(0);
});

it('can set the visitor instance', function (): void {
    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    Container::getInstance()->make(Views::class)
        ->forViewable($this->post)
        ->useVisitor(
            Container::getInstance()->make(TestVisitor::class)
        )
        ->record();

    Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

    expect(View::count())->toBe(2);
});
