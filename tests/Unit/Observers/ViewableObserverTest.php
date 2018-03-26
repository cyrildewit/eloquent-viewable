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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Observers;

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

/**
 * Class ViewableObserverTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewableObserverTest extends TestCase
{
    /** @test */
    public function it_removes_all_views_when_deleted()
    {
        $post = factory(Post::class)->create();

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_id', $post->getKey())->count());

        $post->delete();

        $this->assertEquals(0, View::where('viewable_id', $post->getKey())->count());
    }

    /** @test */
    public function it_does_not_removes_all_views_when_deleted_if_removeViewsOnDelte_was_false()
    {
        $post = factory(Post::class)->create();
        $post->removeViewsOnDelete = false;

        $post->addView();
        $post->addView();
        $post->addView();

        $this->assertEquals(3, View::where('viewable_id', $post->getKey())->count());

        $post->delete();

        $this->assertEquals(3, View::where('viewable_id', $post->getKey())->count());
    }
}
