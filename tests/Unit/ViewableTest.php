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

use Request;
use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use Illuminate\Support\Facades\Config;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Support\IpAddress;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

/**
 * Class ViewableTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableTest extends TestCase
{
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /** @test */
    public function getViews_can_return_the_total_number_of_views()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function getViews_can_return_the_total_number_of_views_since_startdatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);

        $this->assertEquals(3, $post->getViews());
        $this->assertEquals(2, $post->getViews(Period::since(Carbon::parse('2018-01-02 01:00:00'))));
    }

    /** @test */
    public function getViews_can_return_the_total_number_of_views_upto_enddatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);

        $this->assertEquals(3, $post->getViews());
        $this->assertEquals(2, $post->getViews(Period::upto(Carbon::parse('2018-01-02 01:00:00'))));
    }

    /** @test */
    public function getViews_can_return_the_total_number_of_views_between_startdatetime_and_enddatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00')]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00')]); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00')]);

        $this->assertEquals(6, $post->getViews());
        $this->assertEquals(4, $post->getViews(Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00'))));
    }

    /** @test */
    public function getUniqueViews_can_return_the_total_number_of_unique_views()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $post->getViews());
        $this->assertEquals(4, $post->getUniqueViews());
    }

    /** @test */
    public function getUniqueViews_can_return_the_total_number_of_unique_views_since_startdatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $post->getViews());
        $this->assertEquals(3, $post->getUniqueViews(Period::since(Carbon::parse('2018-01-04 01:00:00'))));
    }

    /** @test */
    public function getUniqueViews_can_return_the_total_number_of_unique_views_upto_enddatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $post->getViews());
        $this->assertEquals(2, $post->getUniqueViews(Period::upto(Carbon::parse('2018-01-03 01:00:00'))));
    }

    /** @test */
    public function getUniqueViews_can_return_the_total_number_of_unique_views_between_startdatetime_and_enddatetime()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00'), 'visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00'), 'visitor' => 'visitor_two']); // start
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00'), 'visitor' => 'visitor_two']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00'), 'visitor' => 'visitor_three']);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00'), 'visitor' => 'visitor_four']); // end
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00'), 'visitor' => 'visitor_one']);

        $this->assertEquals(6, $post->getViews());
        $this->assertEquals(3, $post->getUniqueViews(Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00'))));
    }

    /** @test */
    public function getViews_can_return_the_total_number_of_views_from_the_cache()
    {
        Config::set('eloquent-viewable.cache.enabled', true);
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, $post->getViews());

        $post->addView();
        $post->addView();

        $this->assertEquals(3, $post->getViews());
    }

    /** @test */
    public function addView_can_save_a_view_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();

        $this->assertEquals(1, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addView_can_save_multiple_views_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addView_does_not_save_views_of_bots_if_configured()
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

        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addView_does_not_save_views_of_visitors_with_dnt_header()
    {
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.honor_dnt', true);
        Request::instance()->headers->set('HTTP_DNT', 1);

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addView_does_not_save_views_of_ignored_ip_addresses()
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

        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());

        // Test ip address: 10.10.30.40
        $this->app->bind(IpAddress::class, function ($app) {
            return new class {
                public function get()
                {
                    return '10.10.30.40';
                }
            };
        });

        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewWithExpiryDate_can_save_a_view_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addViewWithExpiryDate(Carbon::now()->addDays(5));

        $this->assertEquals(1, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function addViewWithExpiryDate_does_not_save_views_to_a_model_if_not_expired()
    {
        $post = factory(Post::class)->create();

        $post->addViewWithExpiryDate(Carbon::now()->addDays(5));
        $post->addViewWithExpiryDate(Carbon::now()->addDays(5));
        $post->addViewWithExpiryDate(Carbon::now()->addDays(5));

        $this->assertEquals(1, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function removeViews_can_remove_all_views_from_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_type', $post->getMorphClass())->count());

        $post->removeViews();

        $this->assertEquals(0, View::where('viewable_type', $post->getMorphClass())->count());
    }

    /** @test */
    public function applyScopeOrderByViews_can_order_viewables_by_views_in_descending_order()
    {
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        $postOne->addView();
        $postOne->addView();
        $postOne->addView();

        $postTwo->addView();

        $postThree->addView();
        $postThree->addView();

        $posts = Post::orderByViews()->pluck('id');

        $this->assertEquals(collect([1, 3, 2]), $posts);
    }

    /** @test */
    public function applyScopeOrderByViews_can_order_viewables_by_views_in_ascending_order()
    {
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        $postOne->addView();
        $postOne->addView();
        $postOne->addView();

        $postTwo->addView();

        $postThree->addView();
        $postThree->addView();

        $posts = Post::orderByViews('asc')->pluck('id');

        $this->assertEquals(collect([2, 3, 1]), $posts);
    }

    /** @test */
    public function applyScopeOrderByUniqueViews_can_order_viewables_by_unique_views_in_descending_order()
    {
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_three']);

        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_one']);

        TestHelper::createNewView($postThree, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['visitor' => 'visitor_two']);

        $posts = Post::orderByUniqueViews()->pluck('id');

        $this->assertEquals(collect([1, 3, 2]), $posts);
    }

    /** @test */
    public function applyScopeOrderByUniqueViews_can_order_viewables_by_unique_views_in_ascending_order()
    {
        $postOne = factory(Post::class)->create();
        $postTwo = factory(Post::class)->create();
        $postThree = factory(Post::class)->create();

        TestHelper::createNewView($postOne, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_two']);
        TestHelper::createNewView($postOne, ['visitor' => 'visitor_three']);

        TestHelper::createNewView($postTwo, ['visitor' => 'visitor_one']);

        TestHelper::createNewView($postThree, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($postThree, ['visitor' => 'visitor_two']);

        $posts = Post::orderByUniqueViews('asc')->pluck('id');

        $this->assertEquals(collect([2, 3, 1]), $posts);
    }
}
