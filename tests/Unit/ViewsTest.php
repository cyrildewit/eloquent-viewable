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

namespace CyrildeWit\EloquentViewable\Tests\Unit;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Views;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Contracts\IpAddressResolver;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Apartment;

class ViewsTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post */
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

        $this->assertEquals('someValue', views()->newMethod());
    }

    /** @test */
    public function it_can_record_a_view()
    {
        views($this->post)->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_multiple_views()
    {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, View::count());
    }

    /** @test */
    public function it_can_record_a_view_with_a_session_delay()
    {
        views($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        views($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_record_a_view_under_a_collection()
    {
        views($this->post)
            ->collection('customCollection')
            ->record();

        views($this->post)->record();

        $this->assertEquals(1, View::where('collection', 'customCollection')->count());
    }

    /** @test */
    public function it_can_record_a_view_with_a_custom_ip_address()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '100.13.20.120',
        ]);

        views($this->post)
            ->overrideIpAddress('128.42.77.5')
            ->record();

        views($this->post)
            ->overrideIpAddress('100.13.20.120')
            ->record();

        $this->assertEquals(1, View::count());
    }

    /** @test */
    public function it_can_count_the_views()
    {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, views($this->post)->count());
    }

    /** @test */
    public function it_can_count_the_unique_views()
    {
        TestHelper::createNewView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($this->post, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($this->post, ['visitor' => 'visitor_two']);

        $this->assertEquals(2, views($this->post)->unique()->count());
    }

    /** @test */
    public function it_can_count_the_views_of_a_period()
    {
        Carbon::setTestNow(Carbon::now());

        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-01-10')]);
        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-01-15')]);
        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-02-10')]);
        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-02-15')]);
        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-03-10')]);
        TestHelper::createNewView($this->post, ['viewed_at' => Carbon::parse('2018-03-15')]);

        $this->assertEquals(6, views($this->post)->period(Period::since(Carbon::parse('2018-01-10')))->count());
        $this->assertEquals(4, views($this->post)->period(Period::upto(Carbon::parse('2018-02-15')))->count());
        $this->assertEquals(4, views($this->post)->period(Period::create(Carbon::parse('2018-01-15'), Carbon::parse('2018-03-10')))->count());
    }

    /** @test */
    public function it_can_count_the_views_with_a_collection()
    {
        views($this->post)->collection('custom')->record();
        views($this->post)->collection('custom')->record();
        views($this->post)->record();

        $this->assertEquals(2, views($this->post)->collection('custom')->count());
        $this->assertEquals(1, views($this->post)->count());
    }

    /** @test */
    public function it_can_destroy_the_views()
    {
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);

        views($this->post)->destroy();

        $this->assertEquals(0, views($this->post)->count());
    }

    /** @test */
    public function it_can_count_the_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        TestHelper::createNewView($postOne);
        TestHelper::createNewView($postTwo);
        TestHelper::createNewView($postTwo);
        TestHelper::createNewView($apartment);
        TestHelper::createNewView($apartment);

        $this->assertEquals(3, app(Views::class)->countByType(Post::class));
        $this->assertEquals(3, app(Views::class)->countByType($postOne));
    }

    /** @test */
    public function it_can_count_the_unique_views_by_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        TestHelper::createNewView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($apartment, ['visitor' => 'visitor_three']);
        TestHelper::createNewView($apartment, ['visitor' => 'visitor_one']);

        $this->assertEquals(2, app(Views::class)->unique()->countByType(Post::class));
        $this->assertEquals(2, app(Views::class)->unique()->countByType($postOne));
    }

    /** @test */
    public function it_can_remember_the_views_counts()
    {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, views($this->post)->remember()->count());

        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, views($this->post)->remember()->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_with_custom_lifetime()
    {
        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, views($this->post)->remember(10)->count());

        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(3, views($this->post)->remember(10)->count());
    }

    /** @test */
    public function it_can_remember_the_views_counts_of_a_type()
    {
        $postOne = $this->post;
        $postTwo = factory(Post::class)->create();
        $apartment = factory(Apartment::class)->create();

        views($postOne)->record();
        views($postTwo)->record();
        views($postTwo)->record();
        views($apartment)->record();
        views($apartment)->record();

        $this->assertEquals(3, views()->remember()->countByType(Post::class));

        views($postTwo)->record();
        views($apartment)->record();

        $this->assertEquals(3, views()->remember()->countByType(Post::class));
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

        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_visitors_with_dnt_header()
    {
        Config::set('eloquent-viewable.honor_dnt', true);
        Request::instance()->headers->set('HTTP_DNT', 1);

        views($this->post)->record();
        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_record_views_from_ignored_ip_addresses()
    {
        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        // Test ip address: 127.20.22.6
        $this->app->bind(IpAddressResolver::class, function ($app) {
            return new class implements IpAddressResolver {
                public function resolve(): string
                {
                    return '127.20.22.6';
                }
            };
        });

        views($this->post)->record();
        views($this->post)->record();

        $this->assertEquals(0, View::count());

        // Test ip address: 10.10.30.40
        $this->app->bind(IpAddressResolver::class, function ($app) {
            return new class implements IpAddressResolver {
                public function resolve(): string
                {
                    return '10.10.30.40';
                }
            };
        });

        $this->assertEquals(0, View::count());
    }
}
