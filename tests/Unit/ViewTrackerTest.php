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

use CyrildeWit\EloquentViewable\ViewTracker;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Location;

/**
 * Class ViewTrackerTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewTrackerTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_class()
    {
        $tracker = $this->app->make(ViewTracker::class);

        $this->assertInstanceOf(ViewTracker::class, $tracker);
    }

    /** @test */
    public function getViewsCountByType_can_give_us_the_number_of_views_of_the_given_class_type()
    {
        $post = factory(Post::class)->create();
        $location = factory(Location::class)->create();

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        TestHelper::createNewView($location);
        TestHelper::createNewView($location);

        $this->assertEquals(3, ViewTracker::getViewsCountByType(Post::class));
        $this->assertEquals(2, ViewTracker::getViewsCountByType(Location::class));
    }

    /** @test */
    public function getViewsCountByType_can_give_us_the_number_of_views_of_the_given_classses()
    {
        $tracker = $this->app->make(ViewTracker::class);
        $post = factory(Post::class)->create();
        $location = factory(Location::class)->create();

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        TestHelper::createNewView($location);
        TestHelper::createNewView($location);

        $this->assertEquals(collect([
            Post::class => 3,
            Location::class => 2,
        ]), ViewTracker::getViewsCountByTypes([Post::class, Location::class]));
    }

    /** @test */
    public function getViewsCountByType_can_give_us_the_number_of_views_of_the_given_class_type_from_the_cache()
    {
        $tracker = $this->app->make(ViewTracker::class);
        $post = factory(Post::class)->create();
        $location = factory(Location::class)->create();

        TestHelper::createNewView($post);
        TestHelper::createNewView($post);
        TestHelper::createNewView($post);

        TestHelper::createNewView($location);
        TestHelper::createNewView($location);

        $this->assertEquals(3, ViewTracker::getViewsCountByType(Post::class));
        $this->assertEquals(2, ViewTracker::getViewsCountByType(Location::class));

        TestHelper::createNewView($post);
        TestHelper::createNewView($location);

        $this->assertEquals(3, ViewTracker::getViewsCountByType(Post::class));
        $this->assertEquals(2, ViewTracker::getViewsCountByType(Location::class));
    }
}
