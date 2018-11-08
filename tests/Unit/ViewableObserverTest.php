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

use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestHelper;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

class ViewableObserverTest extends TestCase
{
    /** @var \CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post */
    protected $post;

    public function setUp()
    {
        parent::setUp();

        $this->post = factory(Post::class)->create();
    }

    /** @test */
    public function it_can_destroy_all_views_when_viewable_gets_deleted()
    {
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);

        $this->assertEquals(3, View::count());

        $this->post->delete();

        $this->assertEquals(0, View::count());
    }

    /** @test */
    public function it_does_not_destroy_all_views_when_viewable_gets_deleted_and_removeViewsOnDelete_is_set_to_false()
    {
        $this->post->removeViewsOnDelete = false;

        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);
        TestHelper::createNewView($this->post);

        $this->assertEquals(3, View::count());

        $this->post->delete();

        $this->assertEquals(3, View::count());
    }
}
