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

namespace CyrildeWit\EloquentViewable\Tests\Unit\Models;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\View;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Tests\Stubs\Models\Post;

class ViewTest extends TestCase
{
    protected function tearDown()
    {
        Carbon::setTestNow();
    }

    /** @test */
    public function it_can_have_a_custom_connection_through_config_file()
    {
        config(['eloquent-viewable.models.view.connection', 'sqlite']);

        $this->assertEquals('sqlite', (new View)->getConnection()->getName());
    }

    /** @test */
    public function it_can_fill_visitor()
    {
        $view = new View([
            'visitor' => 'uniqueString',
        ]);

        $this->assertEquals('uniqueString', $view->getAttribute('visitor'));
    }

    /** @test */
    public function it_can_fill_visitor_with_null()
    {
        $view = new View([
            'visitor' => null,
        ]);

        $this->assertNull($view->getAttribute('visitor'));
    }

    public function it_can_fill_collection()
    {
        $view = new View([
            'collection' => null,
        ]);

        $this->assertNull($view->getAttribute('collection'));
    }

    /** @test */
    public function it_can_fill_viewed_at()
    {
        Carbon::setTestNow($now = Carbon::create(2018, 1, 12));

        $view = new View([
            'viewed_at' => $now,
        ]);

        $this->assertEquals('2018-01-12', $view->viewed_at->format('Y-m-d'));
    }

    /** @test */
    public function it_can_belong_to_viewable_model()
    {
        $post = factory(Post::class)->create();

        $view = factory(View::class)->create([
            'viewable_id' => $post->getKey(),
            'viewable_type' => $post->getMorphClass(),
        ]);

        $this->assertInstanceOf(Post::class, View::first()->viewable);
    }
}
