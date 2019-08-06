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

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Views;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\TestClasses\Models\Post;

class ViewsHelperTest extends TestCase
{
    /** @test */
    public function it_accepts_null_as_viewable()
    {
        $this->assertInstanceOf(Views::class, views());
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
