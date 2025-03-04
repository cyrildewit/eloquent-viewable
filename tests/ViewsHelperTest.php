<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use ArgumentCountError;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;
use CyrildeWit\EloquentViewable\Views;

class ViewsHelperTest extends TestCase
{
    public function test_it_does_not_accept_null_as_a_valid_viewable(): void
    {
        $this->expectException(ArgumentCountError::class);

        views();
    }

    public function test_it_accepts_a_fully_qualified_class_name_as_viewable(): void
    {
        $this->assertInstanceOf(Views::class, views(Post::class));
    }

    public function test_it_accepts_an_empty_model_instance_as_viewable(): void
    {
        $this->assertInstanceOf(Views::class, views(new Post()));
    }
}
