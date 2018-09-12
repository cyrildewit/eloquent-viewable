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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Jobs;

use Config;
use Illuminate\Support\Facades\Queue;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Jobs\ProcessView;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

/**
 * Class ProcessViewTest.
 *
 * @author Cyril de Wit <github@cyrildewit.nl>
 */
class ProcessViewTest extends TestCase
{
    /** @test */
    public function it_gets_pushed_to_the_queue_when_saving_views()
    {
        Queue::fake();

        $post = factory(Post::class)->create();
        Config::set('eloquent-viewable.jobs.store_new_view.enabled', true);

        $post->addView();

        Queue::assertPushed(ProcessView::class);
    }
}
