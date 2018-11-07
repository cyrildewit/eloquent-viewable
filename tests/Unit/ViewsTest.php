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
use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Period;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Apartment;

/**
 * Class ViewsTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
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
    public function it_can_record_a_view_under_a_tag()
    {
        views($this->post)
            ->tag('customTag')
            ->record();

        $this->assertEquals(1, View::where('tag', 'customTag')->count());
    }

    // /** @test */
    // public function it_can_record_a_view_under_multiple_tag()
    // {
    //     views($this->post)
    //         ->tag('firstTag', 'secondTag')
    //         ->record();

    //     $this->assertEquals(1, View::count());
    // }

    /** @test */
    public function it_can_count_the_views()
    {
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);

        $this->assertEquals(3, views($this->post)->count());
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

        $this->assertEquals(3, views()->countByType(Post::class));
    }
}
