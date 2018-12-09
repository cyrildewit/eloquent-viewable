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

use Session;
use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\ViewSessionHistory;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

class ViewSessionHistoryTest extends TestCase
{
    /** @test */
    public function push_can_add_an_item()
    {
        $post = factory(Post::class)->create();
        $viewHistory = app(ViewSessionHistory::class);
        $postSessionKey = config('eloquent-viewable.session.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass())).'.'.$post->getKey();

        $this->assertFalse(Session::has($postSessionKey));

        $viewHistory->push($post, Carbon::tomorrow());

        $this->assertTrue(Session::has($postSessionKey));
    }

    /** @test */
    public function push_does_not_add_an_item_if_already_added()
    {
        $post = factory(Post::class)->create();
        $postBaseKey = config('eloquent-viewable.session.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
        $viewHistory = app(ViewSessionHistory::class);

        $viewHistory->push($post, Carbon::tomorrow());
        $viewHistory->push($post, Carbon::tomorrow());
        $viewHistory->push($post, Carbon::tomorrow());

        $this->assertCount(1, Session::get($postBaseKey));
    }

    /** @test */
    public function it_can_forget_expired_views()
    {
        $post = factory(Post::class)->create();
        $postNamespacKey = config('eloquent-viewable.session.key').'.'.strtolower(str_replace('\\', '-', $post->getMorphClass()));
        $viewHistory = app(ViewSessionHistory::class);

        $viewHistory->push($post, Carbon::today());
        $viewHistory->push($post, Carbon::today()->addHours(1));
        $viewHistory->push($post, Carbon::today()->addHours(2));

        Carbon::setTestNow(Carbon::tomorrow());

        $viewHistory->push($post, Carbon::today()->addHours(2));

        $this->assertCount(1, Session::get($postNamespacKey));
    }
}
