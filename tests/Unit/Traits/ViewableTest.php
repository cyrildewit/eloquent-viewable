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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Traits;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

/**
 * Class ViewableTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableTest extends TestCase
{
    /** @test */
    public function it_can_add_a_new_view_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();

        $this->assertEquals(1, View::where('id', 1)->count());
    }

    /** @test */
    public function it_can_add_multiple_new_views_to_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_id', 1)->count());
    }

    /** @test */
    public function it_can_return_the_total_number_of_views()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, $post->getViews());
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_since_the_given_date()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, Carbon::parse('2018-01-01 01:00:00')); // 1.1
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 02:00:00')); // 1.2
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 03:00:00')); // 1.3

        TestHelper::createNewView($post, Carbon::parse('2018-02-01 01:00:00')); // 2.1
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 02:00:00')); // 2.2
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 03:00:00')); // 2.3

        TestHelper::createNewView($post, Carbon::parse('2018-03-01 01:00:00')); // 3.1
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 02:00:00')); // 3.2
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 03:00:00')); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // since 1.1 == 8
        $this->assertEquals(8, $post->getViewsSince(Carbon::parse('2018-01-01 01:00:00')));

        // since 1.3 == 6
        $this->assertEquals(6, $post->getViewsSince(Carbon::parse('2018-01-01 03:00:00')));

        // since 3.2 == 1
        $this->assertEquals(1, $post->getViewsSince(Carbon::parse('2018-03-01 02:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_upto_the_given_date()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, Carbon::parse('2018-01-01 01:00:00')); // 1.1
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 02:00:00')); // 1.2
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 03:00:00')); // 1.3

        TestHelper::createNewView($post, Carbon::parse('2018-02-01 01:00:00')); // 2.1
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 02:00:00')); // 2.2
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 03:00:00')); // 2.3

        TestHelper::createNewView($post, Carbon::parse('2018-03-01 01:00:00')); // 3.1
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 02:00:00')); // 3.2
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 03:00:00')); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // upto 1.1 == 0
        $this->assertEquals(0, $post->getViewsUpto(Carbon::parse('2018-01-01 01:00:00')));

        // upto 2.1 == 3
        $this->assertEquals(3, $post->getViewsUpto(Carbon::parse('2018-02-01 01:00:00')));

        // upto 3.2 == 7
        $this->assertEquals(7, $post->getViewsUpto(Carbon::parse('2018-03-01 02:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_between_the_given_date_range()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, Carbon::parse('2018-01-01 01:00:00')); // 1.1
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 02:00:00')); // 1.2
        TestHelper::createNewView($post, Carbon::parse('2018-01-01 03:00:00')); // 1.3

        TestHelper::createNewView($post, Carbon::parse('2018-02-01 01:00:00')); // 2.1
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 02:00:00')); // 2.2
        TestHelper::createNewView($post, Carbon::parse('2018-02-01 03:00:00')); // 2.3

        TestHelper::createNewView($post, Carbon::parse('2018-03-01 01:00:00')); // 3.1
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 02:00:00')); // 3.2
        TestHelper::createNewView($post, Carbon::parse('2018-03-01 03:00:00')); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // since 1.1 & upto 2.1 == 4
        $this->assertEquals(4, $post->getViewsBetween(
            Carbon::parse('2018-01-01 01:00:00'),
            Carbon::parse('2018-02-01 01:00:00')
        ));

        // since 2.1 & upto 3.2  == 5
        $this->assertEquals(5, $post->getViewsBetween(
            Carbon::parse('2018-02-01 01:00:00'),
            Carbon::parse('2018-03-01 02:00:00')
        ));

        // since 2.3 & upto 3.3  == 3
        $this->assertEquals(3, $post->getViewsBetween(
            Carbon::parse('2018-02-01 03:00:00'),
            Carbon::parse('2018-03-01 02:00:00')
        ));
    }
}
