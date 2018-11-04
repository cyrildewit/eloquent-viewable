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
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;
use CyrildeWit\EloquentViewable\Tests\TestHelper;

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
    public function it_can_get_the_views_count()
    {
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);

        $this->assertEquals(3, views($this->post)->getViews());
    }
}
