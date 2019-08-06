<?php

declare(strict_types=1);

/*
 * This file is part of the Eloquent Viewable package.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Support\Facades\Config;
use CyrildeWit\EloquentViewable\Viewer;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Apartment;

class ViewsTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post */
    protected $post;

    public function setUp()
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
    }

    /** @test */
    public function it_is_macroable()
    {
        Views::macro('newMethod', function () {
            return 'someValue';
        });

        $this->assertEquals('someValue', app(Views::class)->newMethod());
    }

    /** @test */
    public function it_can_record_a_view()
    {
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_multiple_views()
    {
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, View::count());
    }

    /** @test */
    public function it_does_not_record_views_if_session_delay_is_active()
    {
        app(Views::class)
            ->forViewable($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        app(Views::class)
            ->forViewable($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_a_view_with_session_delay_where_delay_is_an_integer()
    {
        app(Views::class)
            ->forViewable($this->post)
            ->delayInSession(10)
            ->record();

        app(Views::class)
            ->forViewable($this->post)
            ->delayInSession(10)
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_a_view_under_a_collection()
    {
        app(Views::class)
            ->forViewable($this->post)
            ->collection('customCollection')
            ->record();

        app(Views::class)
            ->forViewable($this->post)
            ->record();

        $this->assertEquals(1, View::where('collection', 'customCollection')->count());
    }

    /** @test */
    public function it_can_record_a_view_with_a_custom_ip_address()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '100.13.20.120',
        ]);

        app(Views::class)
            ->forViewable($this->post)
            ->useIpAddress('128.42.77.5')
            ->record();

        app(Views::class)
            ->forViewable($this->post)
            ->useIpAddress('100.13.20.120')
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_a_view_with_a_custom_ip_address_using_useIpAddress()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '100.13.20.120',
        ]);

        app(Views::class)
            ->forViewable($this->post)
            ->useIpAddress('128.42.77.5')
            ->record();

        app(Views::class)
            ->forViewable($this->post)
            ->useIpAddress('100.13.20.120')
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_count_the_views()
    {
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, app(Views::class)->forViewable($this->post)->count());
    }

    /** @test */
    public function it_can_count_the_unique_views()
    {
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createView($this->post, ['visitor' => 'visitor_two']);

        $this->assertEquals(2, app(Views::class)->forViewable($this->post)->unique()->count());
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

        $this->assertEquals(6, app(Views::class)->forViewable($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count());
        $this->assertEquals(4, app(Views::class)->forViewable($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count());
        $this->assertEquals(4, app(Views::class)->forViewable($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count());
    }

    /** @test */
    public function it_can_count_the_views_with_a_collection()
    {
        app(Views::class)->forViewable($this->post)->collection('custom')->record();
        app(Views::class)->forViewable($this->post)->collection('custom')->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(2, app(Views::class)->forViewable($this->post)->collection('custom')->count());
        $this->assertEquals(1, app(Views::class)->forViewable($this->post)->count());
    }

    /** @test */
    public function it_can_destroy_the_views()
    {
        $post = $this->post;
        $apartment = factory(Apartment::class)->create();

        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($post);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        app(Views::class)->forViewable($post)->destroy();

        $this->assertEquals(0, app(Views::class)->forViewable($post)->count());
    }

    /** @test */
    public function it_can_destroy_the_views_of_a_viewable_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        TestHelper::createView($postOne);
        TestHelper::createView($postOne);
        TestHelper::createView($postOne);
        TestHelper::createView($postTwo);
        TestHelper::createView($postTwo);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        app(Views::class)->forViewable(new Post())->destroy();

        $this->assertEquals(0, app(Views::class)->forViewable(new Post())->count());
    }

    /** @test */
    public function it_can_count_the_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        TestHelper::createView($postOne);
        TestHelper::createView($postTwo);
        TestHelper::createView($postTwo);
        TestHelper::createView($apartment);
        TestHelper::createView($apartment);

        $this->assertEquals(3, app(Views::class)->forViewable(new Post())->count());
    }

    /** @test */
    public function it_can_count_the_unique_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        TestHelper::createView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_two']);
        TestHelper::createView($postTwo, ['visitor' => 'visitor_one']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_three']);
        TestHelper::createView($apartment, ['visitor' => 'visitor_one']);

        $this->assertEquals(2, app(Views::class)->forViewable(new Post())->unique()->count());
        $this->assertEquals(2, app(Views::class)->forViewable(new Post())->unique()->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts()
    {
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, app(Views::class)->forViewable($this->post)->remember()->count());

        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, app(Views::class)->forViewable($this->post)->remember()->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_with_custom_lifetime()
    {
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, app(Views::class)->forViewable($this->post)->remember(10)->count());

        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(3, app(Views::class)->forViewable($this->post)->remember(10)->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_of_a_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        app(Views::class)->forViewable($postOne)->record();
        app(Views::class)->forViewable($postTwo)->record();
        app(Views::class)->forViewable($postTwo)->record();
        app(Views::class)->forViewable($apartment)->record();
        app(Views::class)->forViewable($apartment)->record();

        $this->assertEquals(3, views(Post::class)->remember()->count());

        app(Views::class)->forViewable($postTwo)->record();
        app(Views::class)->forViewable($apartment)->record();

        $this->assertEquals(3, views(Post::class)->remember()->count());
    }

    /** @test */
    public function it_does_not_record_bot_views()
    {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, function () {
            return new class implements CrawlerDetector {
                public function isCrawler(): bool
                {
                    return true;
                }
            };
        });

        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_visitors_with_dnt_header()
    {
        Config::set('eloquent-viewable.honor_dnt', true);

        $this->mock(Viewer::class, function ($mock) {
            $mock->shouldReceive('hasDoNotTrackHeader')->andReturn(true);
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_ignored_ip_addresses()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        $this->mock(Viewer::class, function ($mock) {
            $mock->shouldReceive('ip')->andReturn('127.20.22.6');
            $mock->shouldReceive('isCrawler')->andReturn(false);
        });

        app(Views::class)->forViewable($this->post)->record();
        app(Views::class)->forViewable($this->post)->record();

        $this->assertEquals(0, View::count());
    }
}
