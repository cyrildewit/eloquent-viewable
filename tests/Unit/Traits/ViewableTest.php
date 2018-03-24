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

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 02:00:00')]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 03:00:00')]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 01:00:00')]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 02:00:00')]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 03:00:00')]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 01:00:00')]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 02:00:00')]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 03:00:00')]); // 3.3

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

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 02:00:00')]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 03:00:00')]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 01:00:00')]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 02:00:00')]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 03:00:00')]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 01:00:00')]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 02:00:00')]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 03:00:00')]); // 3.3

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

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 01:00:00')]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 02:00:00')]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-01-01 03:00:00')]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 01:00:00')]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 02:00:00')]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-02-01 03:00:00')]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 01:00:00')]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 02:00:00')]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::parse('2018-03-01 03:00:00')]); // 3.3

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

    /** @test */
    public function it_can_return_the_total_number_of_unique_views()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_one']);
        TestHelper::createNewView($post, ['visitor' => 'visitor_two']);

        $this->assertEquals(2, $post->getUniqueViews());
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_since_the_given_date()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 01:00:00'),
        ]); // 1.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 02:00:00'),
        ]); // 1.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-01-01 03:00:00'),
        ]); // 1.3

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-02-01 01:00:00'),
        ]); // 2.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 02:00:00'),
        ]); // 2.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 03:00:00'),
        ]); // 2.1

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-03-01 01:00:00'),
        ]); // 3.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 02:00:00'),
        ]); // 3.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 03:00:00'),
        ]); // 3.2

        // total views
        $this->assertEquals(9, $post->getViews());

        // since 1.1 == 3
        $this->assertEquals(3, $post->getUniqueViewsSince(Carbon::parse('2018-01-01 01:00:00')));

        // since 1.3 == 3
        $this->assertEquals(3, $post->getUniqueViewsSince(Carbon::parse('2018-01-01 03:00:00')));

        // since 3.1 == 1
        $this->assertEquals(1, $post->getUniqueViewsSince(Carbon::parse('2018-03-01 01:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_upto_the_given_date()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 01:00:00'),
        ]); // 1.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 02:00:00'),
        ]); // 1.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-01-01 03:00:00'),
        ]); // 1.3

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-02-01 01:00:00'),
        ]); // 2.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 02:00:00'),
        ]); // 2.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 03:00:00'),
        ]); // 2.1

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-03-01 01:00:00'),
        ]); // 3.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 02:00:00'),
        ]); // 3.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 03:00:00'),
        ]); // 3.2

        // total views
        $this->assertEquals(9, $post->getViews());

        // upto 1.2 == 1
        $this->assertEquals(1, $post->getUniqueViewsUpto(Carbon::parse('2018-01-01 03:00:00')));

        // upto 2.1 == 2
        $this->assertEquals(2, $post->getUniqueViewsUpto(Carbon::parse('2018-02-01 02:00:00')));

        // upto 3.2 == 3
        $this->assertEquals(3, $post->getUniqueViewsUpto(Carbon::parse('2018-03-01 03:00:00')));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_between_the_given_date_range()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 01:00:00'),
        ]); // 1.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-01-01 02:00:00'),
        ]); // 1.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-01-01 03:00:00'),
        ]); // 1.3

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::parse('2018-02-01 01:00:00'),
        ]); // 2.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 02:00:00'),
        ]); // 2.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-02-01 03:00:00'),
        ]); // 2.3

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::parse('2018-03-01 01:00:00'),
        ]); // 3.1
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 02:00:00'),
        ]); // 3.2
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::parse('2018-03-01 03:00:00'),
        ]); // 3.2

        // total views
        $this->assertEquals(9, $post->getViews());

        // since 1.1 & upto 1.3 == 2
        $this->assertEquals(2, $post->getUniqueViewsBetween(
            Carbon::parse('2018-01-01 01:00:00'),
            Carbon::parse('2018-01-01 03:00:00')
        ));

        // since 1.1 & upto 3.2 == 2
        $this->assertEquals(3, $post->getUniqueViewsBetween(
            Carbon::parse('2018-01-01 01:00:00'),
            Carbon::parse('2018-03-01 03:00:00')
        ));

        // since 2.1 & upto 3.2 == 3
        $this->assertEquals(3, $post->getUniqueViewsBetween(
            Carbon::parse('2018-02-01 01:00:00'),
            Carbon::parse('2018-03-01 02:00:00')
        ));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_seconds()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(10)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(20)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(30)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastSeconds(15));

        $this->assertEquals(6, $post->getViewsOfPastSeconds(25));

        $this->assertEquals(9, $post->getViewsOfPastSeconds(35));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_minutes()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(10)]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(20)]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(30)]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(40)]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(50)]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(60)]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(70)]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(80)]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(90)]); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // past 15 minutes == 1
        $this->assertEquals(1, $post->getViewsOfPastMinutes(15));

        // past 30 minutes == 2
        $this->assertEquals(2, $post->getViewsOfPastMinutes(30));

        // past 60 minutes == 5
        $this->assertEquals(5, $post->getViewsOfPastMinutes(60));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_days()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(10)]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(20)]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(30)]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(40)]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(50)]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(60)]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(70)]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(80)]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(90)]); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // past 15 days == 1
        $this->assertEquals(1, $post->getViewsOfPastDays(15));

        // past 30 days == 2
        $this->assertEquals(2, $post->getViewsOfPastDays(30));

        // past 60 days == 5
        $this->assertEquals(5, $post->getViewsOfPastDays(60));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_weeks()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(10)]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(20)]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(30)]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(40)]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(50)]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(60)]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(70)]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(80)]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(90)]); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // past 15 weeks == 1
        $this->assertEquals(1, $post->getViewsOfPastWeeks(15));

        // past 30 weeks == 2
        $this->assertEquals(2, $post->getViewsOfPastWeeks(30));

        // past 60 weeks == 5
        $this->assertEquals(5, $post->getViewsOfPastWeeks(60));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_months()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(10)]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(20)]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(30)]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(40)]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(50)]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(60)]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(70)]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(80)]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(90)]); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // past 15 months == 1
        $this->assertEquals(1, $post->getViewsOfPastMonths(15));

        // past 30 months == 2
        $this->assertEquals(2, $post->getViewsOfPastMonths(30));

        // past 60 months == 5
        $this->assertEquals(5, $post->getViewsOfPastMonths(60));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_years()
    {
        $post = factory(Post::class)->create();

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(10)]); // 1.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(20)]); // 1.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(30)]); // 1.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(40)]); // 2.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(50)]); // 2.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(60)]); // 2.3

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(70)]); // 3.1
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(80)]); // 3.2
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(90)]); // 3.3

        // total views
        $this->assertEquals(9, $post->getViews());

        // past 15 years == 1
        $this->assertEquals(1, $post->getViewsOfPastYears(15));

        // past 30 years == 2
        $this->assertEquals(2, $post->getViewsOfPastYears(30));

        // past 60 years == 5
        $this->assertEquals(5, $post->getViewsOfPastYears(60));
    }

    /** @test */
    public function it_can_remove_all_views_from_a_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        // Before
        $this->assertEquals(3, View::where('viewable_id', 1)->count());

        // Remove all views from the post
        $post->removeViews();

        // After
        $this->assertEquals(0, View::where('viewable_id', 1)->count());
    }
}
