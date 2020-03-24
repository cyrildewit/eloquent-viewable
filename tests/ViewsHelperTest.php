<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Views;
use ArgumentCountError;

class ViewsHelperTest extends TestCase
{
    /** @test */
    public function it_does_not_accept_null_as_a_valid_viewable()
    {
        $this->expectException(ArgumentCountError::class);

        views();
    }

    /** @test */
    public function it_accepts_a_fully_qualified_class_name_as_viewable()
    {
        $this->assertInstanceOf(Views::class, views(Post::class));
    }

    /** @test */
    public function it_accepts_an_empty_model_instance_as_viewable()
    {
        $this->assertInstanceOf(Views::class, views(new Post()));
    }
}
