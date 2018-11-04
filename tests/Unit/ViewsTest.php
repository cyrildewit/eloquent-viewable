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
    public function it_can_delay_a_view_in_the_session()
    {
        views($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        views($this->post)
            ->delayInSession(Carbon::now()->addMinutes(10))
            ->record();

        $this->assertEquals(1, View::count());
    }
}
