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

use Config;
use Request;
use Carbon\Carbon;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use CyrildeWit\EloquentViewable\Support\Ip;
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
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

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

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(50)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(50)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(50)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(100)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(100)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(100)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(150)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(150)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subSeconds(150)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastSeconds(75));

        $this->assertEquals(6, $post->getViewsOfPastSeconds(125));

        $this->assertEquals(9, $post->getViewsOfPastSeconds(175));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_minutes()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(10)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(20)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMinutes(30)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastMinutes(15));

        $this->assertEquals(6, $post->getViewsOfPastMinutes(25));

        $this->assertEquals(9, $post->getViewsOfPastMinutes(35));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_days()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(10)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(10)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(20)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(20)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(30)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subDays(30)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastDays(15));

        $this->assertEquals(6, $post->getViewsOfPastDays(25));

        $this->assertEquals(9, $post->getViewsOfPastDays(35));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_weeks()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(2)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(4)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subWeeks(6)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastWeeks(3));

        $this->assertEquals(6, $post->getViewsOfPastWeeks(5));

        $this->assertEquals(9, $post->getViewsOfPastWeeks(7));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_months()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(2)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(4)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subMonths(6)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastMonths(3));

        $this->assertEquals(6, $post->getViewsOfPastMonths(5));

        $this->assertEquals(9, $post->getViewsOfPastMonths(7));
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_of_past_years()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(2)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(2)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(4)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(4)]);

        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(6)]);
        TestHelper::createNewView($post, ['viewed_at' => Carbon::now()->subYears(6)]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(3, $post->getViewsOfPastYears(3));

        $this->assertEquals(6, $post->getViewsOfPastYears(5));

        $this->assertEquals(9, $post->getViewsOfPastYears(7));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_seconds()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subSeconds(50),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subSeconds(50),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subSeconds(50),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subSeconds(100),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subSeconds(100),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subSeconds(100),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subSeconds(150),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subSeconds(150),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subSeconds(150),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastSeconds(75));

        $this->assertEquals(3, $post->getUniqueViewsOfPastSeconds(125));

        $this->assertEquals(4, $post->getUniqueViewsOfPastSeconds(175));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_minutes()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMinutes(10),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMinutes(10),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMinutes(10),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMinutes(20),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subMinutes(20),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMinutes(20),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMinutes(30),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subMinutes(30),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subMinutes(30),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastMinutes(15));

        $this->assertEquals(3, $post->getUniqueViewsOfPastMinutes(25));

        $this->assertEquals(4, $post->getUniqueViewsOfPastMinutes(35));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_days()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subDays(10),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subDays(10),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subDays(10),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subDays(20),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subDays(20),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subDays(20),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subDays(30),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subDays(30),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subDays(30),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastDays(15));

        $this->assertEquals(3, $post->getUniqueViewsOfPastDays(25));

        $this->assertEquals(4, $post->getUniqueViewsOfPastDays(35));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_weeks()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subWeeks(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subWeeks(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subWeeks(2),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subWeeks(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subWeeks(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subWeeks(4),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subWeeks(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subWeeks(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subWeeks(6),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastWeeks(3));

        $this->assertEquals(3, $post->getUniqueViewsOfPastWeeks(5));

        $this->assertEquals(4, $post->getUniqueViewsOfPastWeeks(7));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_months()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMonths(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMonths(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMonths(2),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMonths(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subMonths(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subMonths(4),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subMonths(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subMonths(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subMonths(6),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastMonths(3));

        $this->assertEquals(3, $post->getUniqueViewsOfPastMonths(5));

        $this->assertEquals(4, $post->getUniqueViewsOfPastMonths(7));
    }

    /** @test */
    public function it_can_return_the_total_number_of_unique_views_of_past_years()
    {
        $post = factory(Post::class)->create();

        // Set a fixed date for Carbon::now()
        Carbon::setTestNow(Carbon::create(2020, 1, 1, 0, 0, 0));

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subYears(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subYears(2),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subYears(2),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subYears(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subYears(4),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_one',
            'viewed_at' => Carbon::now()->subYears(4),
        ]);

        TestHelper::createNewView($post, [
            'visitor' => 'visitor_two',
            'viewed_at' => Carbon::now()->subYears(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_three',
            'viewed_at' => Carbon::now()->subYears(6),
        ]);
        TestHelper::createNewView($post, [
            'visitor' => 'visitor_four',
            'viewed_at' => Carbon::now()->subYears(6),
        ]);

        // total views
        $this->assertEquals(9, $post->getViews());

        $this->assertEquals(2, $post->getUniqueViewsOfPastYears(3));

        $this->assertEquals(3, $post->getUniqueViewsOfPastYears(5));

        $this->assertEquals(4, $post->getUniqueViewsOfPastYears(7));
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

    /** @test */
    public function it_can_order_by_views_count_in_descending_order()
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

        $posts = Post::orderByViewsCount()->get()->pluck('id');

        $this->assertEquals(collect([1, 3, 2]), $posts);
    }

    /** @test */
    public function it_can_order_by_views_count_in_ascending_order()
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

        $posts = Post::orderByViewsCount('asc')->get()->pluck('id');

        $this->assertEquals(collect([2, 3, 1]), $posts);
    }

    /** @test */
    public function it_can_return_the_total_number_of_views_from_the_cache()
    {
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
    public function it_does_not_save_views_of_bots()
    {
        $post = factory(Post::class)->create();

        // Faking that the visitor is a bot
        app()->bind(CrawlerDetector::class, function () {
            return new class {
                public function isRobot()
                {
                    return true;
                }
            };
        });

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('id', 1)->count());
    }

    /** @test */
    public function it_does_not_save_views_of_visitors_with_dnt_header()
    {
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.honor_dnt', true);
        Request::instance()->headers->set('HTTP_DNT', 1);

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('id', 1)->count());
    }

    /** @test */
    public function it_does_not_save_views_of_ignored_ip_addresses()
    {
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.ignored_ip_addresses', [
            '127.20.22.6',
            '10.10.30.40',
        ]);

        $this->app->bind(Ip::class, function ($app) {
            return new class {
                public function get()
                {
                    return '127.20.22.6';
                }
            };
        });

        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_id', $post->getKey())->count());

        $this->app->bind(Ip::class, function ($app) {
            return new class {
                public function get()
                {
                    return '10.10.30.40';
                }
            };
        });

        $post->addView();
        $post->addView();

        $this->assertEquals(0, View::where('viewable_id', $post->getKey())->count());
    }
}
