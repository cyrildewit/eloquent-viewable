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

use Config;
use Request;
use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\ViewableService;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Support\IpAddress;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

/**
 * Class ViewableServiceTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableServiceTest extends TestCase
{
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /** @test */
    public function it_can_instantiate_service()
    {
        $service = $this->app->make(ViewableService::class);

        $this->assertInstanceOf(ViewableService::class, $service);
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_views()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        $this->assertEquals(3, $service->getViewsCount($post));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_views_since_startdatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);

        $this->assertEquals(3, $service->getViewsCount($post));
        $this->assertEquals(2, $service->getViewsCount($post, Period::create(Carbon::parse('2018-01-02 01:00:00'))));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_views_upto_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);

        $this->assertEquals(3, $service->getViewsCount($post));
        $this->assertEquals(2, $service->getViewsCount($post, Period::create(null, Carbon::parse('2018-01-02 01:00:00'))));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_views_between_startdatetime_and_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]); //
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00')]); //
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00')]);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(4, $service->getViewsCount($post, Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00'))));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_unique_views()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(4, $service->getViewsCount($post, null, true));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_unique_views_since_startdatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(3, $service->getViewsCount($post, Period::create(Carbon::parse('2018-01-04 01:00:00')), true));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_unique_views_upto_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(2, $service->getViewsCount($post, Period::create(null, Carbon::parse('2018-01-03 01:00:00')), true));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_unique_views_between_startdatetime_and_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(3, $service->getViewsCount($post, Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00')), true));
    }

    /** @test */
    public function getViewsCount_can_return_the_total_number_of_views_from_the_cache()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        $this->assertEquals(3, $service->getViewsCount($post));

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        $this->assertEquals(3, $service->getViewsCount($post));
    }

    /** @test */
    public function getUniqueViewsCount_can_return_the_total_number_of_unique_views()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_one']);

        $this->assertEquals(4, $service->getUniqueViewsCount($post));
    }

    /** @test */
    public function getUniqueViewsCount_can_return_the_total_number_of_unique_views_since_startdatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(3, $service->getUniqueViewsCount($post, Period::create(Carbon::parse('2018-01-04 01:00:00'))));
    }

    /** @test */
    public function getUniqueViewsCount_can_return_the_total_number_of_unique_views_upto_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(2, $service->getUniqueViewsCount($post, Period::create(null, Carbon::parse('2018-01-03 01:00:00'))));
    }

    /** @test */
    public function getUniqueViewsCount_can_return_the_total_number_of_unique_views_between_startdatetime_and_enddatetime()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $service->getViewsCount($post));
        $this->assertEquals(3, $service->getUniqueViewsCount($post, Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00'))));
    }

    /** @test */
    public function addViewTo_can_save_a_view_to_a_model()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        $service->addViewTo($post);

        $this->assertEquals(1, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewTo_can_save_multiple_views_to_a_model()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        $service->addViewTo($post);
        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(3, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewTo_does_not_save_views_of_bots_if_configured()
    {
        // Faking that the visitor is a bot
        $this->app->bind(CrawlerDetector::class, function () {
            return new class {
                public function isBot()
                {
                    return true;
                }
            };
        });

        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        $service->addViewTo($post);
        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewTo_does_not_save_views_of_visitors_with_dnt_header()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.honor_dnt', true);
        Request::instance()->headers->set('HTTP_DNT', 1);

        $service->addViewTo($post);
        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewTo_does_not_save_views_of_ignored_ip_addresses()
    {
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        // Test ip address: 127.20.22.6
        $this->app->bind(IpAddress::class, function ($app) {
            return new class {
                public function get()
                {
                    return '127.20.22.6';
                }
            };
        });

        $service = $this->app->make(ViewableService::class);

        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());

        // Test ip address: 10.10.30.40
        $this->app->bind(Ip::class, function ($app) {
            return new class {
                public function get()
                {
                    return '10.10.30.40';
                }
            };
        });

        $service = $this->app->make(ViewableService::class);

        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function removeModelViews_can_remove_all_views_from_a_model()
    {
        $service = $this->app->make(ViewableService::class);
        $post = factory(Post::class)->create();

        $service->addViewTo($post);
        $service->addViewTo($post);
        $service->addViewTo($post);

        $this->assertEquals(3, View::where('viewable_type', $post->getMorphClass())->count());

        $service->removeModelViews($post);

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function applyScopeOrderByViewsCount_can_order_viewables_by_views_in_descending_order()
    {
        $service = $this->app->make(ViewableService::class);
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        $service->addViewTo($postOne);
        $service->addViewTo($postOne);
        $service->addViewTo($postOne);

        $service->addViewTo($postTwo);

        $service->addViewTo($postThree);
        $service->addViewTo($postThree);

        $posts = $service->applyScopeOrderByViewsCount(Post::query())->pluck('id');

        $this->assertEquals(collect([1, 3, 2]), $posts);
    }

    /** @test */
    public function applyScopeOrderByViewsCount_can_order_viewables_by_views_in_ascending_order()
    {
        $service = $this->app->make(ViewableService::class);
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        $service->addViewTo($postOne);
        $service->addViewTo($postOne);
        $service->addViewTo($postOne);

        $service->addViewTo($postTwo);

        $service->addViewTo($postThree);
        $service->addViewTo($postThree);

        $posts = $service->applyScopeOrderByViewsCount(Post::query(), 'asc')->pluck('id');

        $this->assertEquals(collect([2, 3, 1]), $posts);
    }
}
