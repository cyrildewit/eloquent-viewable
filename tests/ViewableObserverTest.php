<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\View;

class ViewableObserverTest extends TestCase
{
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post = Post::factory()->create();
    }

    public function test_it_can_destroy_all_views_when_viewable_gets_deleted(): void
    {
        TestHelper::createView($this->post);
        TestHelper::createView($this->post);
        TestHelper::createView($this->post);

        $this->assertEquals(3, View::count());

        $this->post->delete();

        $this->assertEquals(0, View::count());
    }

    public function test_it_does_not_destroy_all_views_when_viewable_gets_deleted_and_remove_views_on_delete_is_set_to_false(): void
    {
        $this->post->removeViewsOnDelete = false;

        TestHelper::createView($this->post);
        TestHelper::createView($this->post);
        TestHelper::createView($this->post);

        $this->assertEquals(3, View::count());

        $this->post->delete();

        $this->assertEquals(3, View::count());
    }
}
