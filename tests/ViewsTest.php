<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestClasses\TestVisitor;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Visitor;
use DateTime;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;

class ViewsTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post */
    protected $post;

    public function setUp(): void
    {
        parent::setUp();

        $this->post = Post::factory()->create();
    }

    /** @test */
    public function it_is_macroable()
    {
        Views::macro('newMethod', function () {
            return 'someValue';
        });

        $this->assertEquals('someValue', Container::getInstance()->make(Views::class)->newMethod());
    }

    /** @test */
    public function it_can_record_a_view()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_multiple_views()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, View::count());
    }

    /** @test */
    public function it_throws_an_exception_when_recording_a_view_for_a_viewable_type()
    {
        $this->expectException(Exception::class);

        Container::getInstance()->make(Views::class)
            ->forViewable(new Post())
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();
    }

    /** @test */
    public function it_does_not_record_views_if_cooldown_is_active()
    {
        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_does_not_record_views_if_session_delay_is_active_with_collection()
    {
        views($this->post)
            ->collection('test')
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        views($this->post)
            ->collection('test')
            ->cooldown(Carbon::now()->addMinutes(10))
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_a_view_with_cooldown_where_lifetime_is_an_integer()
    {
        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(10)
            ->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(10)
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_does_not_record_views_if_cooldown_is_active_with_collection()
    {
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

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_remove_a_cooldown()
    {
        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(null)
            ->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->cooldown(null)
            ->record();

        $this->assertEquals(2, View::count());
    }

    /** @test */
    public function it_can_record_a_view_under_a_collection()
    {
        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->collection('customCollection')
            ->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->record();

        $this->assertEquals(1, View::where('collection', 'customCollection')->count());
    }

    /** @test */
    public function it_can_remove_the_collection()
    {
        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->collection(null)
            ->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->record();

        $this->assertEquals(2, View::where('collection', null)->count());
    }

    /** @test */
    public function it_can_count_the_views()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->count());
    }

    /** @test */
    public function it_can_count_the_unique_views()
    {
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_two']);

        $this->assertEquals(2, Container::getInstance()->make(Views::class)->forViewable($this->post)->unique()->count());
    }

    /** @test */
    public function it_can_count_the_views_of_a_period()
    {
        Carbon::setTestNow(Carbon::now());

        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-01-15')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-02-15')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-10')]);
        TestHelper::createView($this->post, ['viewed_at' => Carbon::parse('2018-03-15')]);

        $this->assertEquals(6, Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count());
        $this->assertEquals(4, Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count());
        $this->assertEquals(4, Container::getInstance()->make(Views::class)->forViewable($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count());
    }

    /** @test */
    public function it_can_remove_the_period()
    {
        Carbon::setTestNow(Carbon::now());

        TestHelper::createView($this->post);
        TestHelper::createView($this->post);

        $this->assertEquals(2, Container::getInstance()->make(Views::class)->forViewable($this->post)->period(null)->count());
    }

    /** @test */
    public function it_can_count_the_views_with_a_collection()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(2, Container::getInstance()->make(Views::class)->forViewable($this->post)->collection('custom')->count());
        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->count());
    }

    /** @test */
    public function it_can_destroy_the_views()
    {
        $post = $this->post;
        $apartment = Apartment::factory()->create();

        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        Container::getInstance()->make(Views::class)->forViewable($post)->destroy();

        $this->assertEquals(0, Container::getInstance()->make(Views::class)->forViewable($post)->count());
    }

    /** @test */
    public function it_can_destroy_the_views_of_a_viewable_type()
    {
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

        Container::getInstance()->make(Views::class)->forViewable(new Post())->destroy();

        $this->assertEquals(0, Container::getInstance()->make(Views::class)->forViewable(new Post())->count());
    }

    /** @test */
    public function it_can_count_the_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        TestHelper::createView($postOne);
        TestHelper::createView($postTwo);
        TestHelper::createView($postTwo);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable(new Post())->count());
    }

    /** @test */
    public function it_can_count_the_unique_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_one']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_three']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_one']);

        $this->assertEquals(2, Container::getInstance()->make(Views::class)->forViewable(new Post())->unique()->count());
        $this->assertEquals(2, Container::getInstance()->make(Views::class)->forViewable(new Post())->unique()->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count());

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count());
    }

    /** @test */
    public function it_can_remove_the_remember_lifetime()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->count());

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(5, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(60)->remember(null)->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_with_custom_lifetime_as_integers()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(10)->count());

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(10)->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_with_custom_lifetime_as_DateTimeInterface()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(new DateTime('2050-01-01'))->count());

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(new DateTime('2050-01-01'))->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_with_custom_lifetime_as_CarbonInterface()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(Carbon::now()->addHours(2))->count());

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, Container::getInstance()->make(Views::class)->forViewable($this->post)->remember(Carbon::now()->addHours(2))->count());
    }

    /** @test */
    public function it_throws_an_exception_when_remember_lifetime_is_of_incorrect_type()
    {
        $this->expectException(Exception::class);

        Container::getInstance()->make(Views::class)->forViewable($this->post)->remember('not good')->count();
    }

    /** @test */
    public function it_can_remember_the_views_counts_of_a_type()
    {
        $postOne = $this->post;
        $postTwo = Post::factory()->create();
        $apartment = Apartment::factory()->create();

        Container::getInstance()->make(Views::class)->forViewable($postOne)->record();
        Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
        Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
        Container::getInstance()->make(Views::class)->forViewable($apartment)->record();
        Container::getInstance()->make(Views::class)->forViewable($apartment)->record();

        $this->assertEquals(3, views(Post::class)->remember(60)->count());

        Container::getInstance()->make(Views::class)->forViewable($postTwo)->record();
        Container::getInstance()->make(Views::class)->forViewable($apartment)->record();

        $this->assertEquals(3, views(Post::class)->remember(60)->count());
    }

    /** @test */
    public function it_does_not_record_bot_views()
    {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, function () {
            return new class implements CrawlerDetector
            {
                public function isCrawler(): bool
                {
                    return true;
                }
            };
        });

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_visitors_with_dnt_header()
    {
        Config::set('eloquent-viewable.honor_dnt', true);

        $this->mock(Visitor::class, function ($mock) {
            $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_ignored_ip_addresses()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        $this->mock(Visitor::class, function ($mock) {
            $mock->shouldReceive('ip')->andReturn('127.20.22.6');
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_can_set_the_visitor_instance()
    {
        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        Container::getInstance()->make(Views::class)
            ->forViewable($this->post)
            ->useVisitor(
                Container::getInstance()->make(TestVisitor::class)
            )
            ->record();

        Container::getInstance()->make(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(2, View::count());
    }
}
