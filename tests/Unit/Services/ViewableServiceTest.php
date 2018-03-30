<?php

declare(strict_types=1);

/*
 * This file is part of Eloquent Viewable.
 *
 * (c) Cyril de Wit <github@cyrildewit.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CyrildeWit\EloquentViewable\Tests\Unit\Services;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Services\ViewableService;

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
       TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-02 01:00:00')]);//
       TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-03 01:00:00')]);
       TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-04 01:00:00')]);
       TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-05 01:00:00')]);//
       TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-06 01:00:00')]);

       $this->assertEquals(6, $service->getViewsCount($post));
       $this->assertEquals(4, $service->getViewsCount($post, Period::create(Carbon::parse('2018-01-02 01:00:00'), Carbon::parse('2018-01-05 01:00:00'))));
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

       $posts = $service->applyScopeOrderByViewsCount(Post::query())->get()->pluck('id');

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

       $posts = $service->applyScopeOrderByViewsCount(Post::query(), 'asc')->get()->pluck('id');

        $this->assertEquals(collect([2, 3, 1]), $posts);
   }
}
