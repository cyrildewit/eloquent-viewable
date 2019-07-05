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

namespace CyrildeWit\EloquentViewable\Tests\Feature;

use Illuminate\Http\Request;
use CyrildeWit\EloquentViewable\Viewer;
use CyrildeWit\EloquentViewable\Tests\TestCase;
use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;

class ViewerTest extends TestCase
{
    /** @test */
    public function it_can_get_the_ip_address_from_the_request()
    {
        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('ip')->once()->andReturn('241.224.55.106');
        });

        $viewer = app(Viewer::class);

        $this->assertEquals('241.224.55.106', $viewer->ip());
    }

    /** @test */
    public function it_can_determine_if_the_viewer_is_a_crawler()
    {
        $this->mock(CrawlerDetector::class, function ($mock) {
            $mock->shouldReceive('isCrawler')->once()->andReturn(true);
        });

        $viewer = app(Viewer::class);

        $this->assertTrue($viewer->isCrawler());
    }
}
