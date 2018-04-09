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

namespace CyrildeWit\EloquentViewable\Tests\Unit;

use Config;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

/**
 * Class ViewableObserverTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ViewTest extends TestCase
{
    /** @test */
    public function it_can_save_views_with_a_custom_connection()
    {
        $post = factory(Post::class)->create();

        Config::set('eloquent-viewable.models.view.connection', 'sqlite');

        $post->addView();

        $this->assertInstanceOf(Post::class, View::where('viewable_id', $post->getKey())->firstOrFail()->viewable);
    }

    /** @test */
    public function it_can_belong_to_viewable_model()
    {
        $post = factory(Post::class)->create();

        $post->addView();

        $this->assertInstanceOf(Post::class, View::first()->viewable);
    }
}
