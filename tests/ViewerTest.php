<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Viewer;
use CyrildeWit\EloquentViewable\VisitorCookieRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

class ViewerTest extends TestCase
{
    /** @test */
    public function it_can_get_the_id_from_the_visitor_cookie_repository()
    {
        $this->mock(VisitorCookieRepository::class, function ($mock) {
            $mock->shouldReceive('get')->once()->andReturn('241.224.55.106');
        });

        $viewer = Container::getInstance()->make(Viewer::class);

        $this->assertEquals('241.224.55.106', $viewer->id());
    }

    /** @test */
    public function it_can_get_the_ip_address_from_the_request()
    {
        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('ip')->once()->andReturn('241.224.55.106');
        });

        $viewer = Container::getInstance()->make(Viewer::class);

        $this->assertEquals('241.224.55.106', $viewer->ip());
    }

    /** @test */
    public function it_can_determine_if_the_viewer_has_a_do_not_tracker_header_from_the_request()
    {
        $this->mock(Request::class, function ($mock) {
            $mock->shouldReceive('header')->once()->andReturn('1');
        });

        $viewer = Container::getInstance()->make(Viewer::class);

        $this->assertTrue($viewer->hasDoNotTrackHeader());
    }

    /** @test */
    public function it_can_determine_if_the_viewer_is_a_crawler_from_the_crawler_detector()
    {
        $this->mock(CrawlerDetector::class, function ($mock) {
            $mock->shouldReceive('isCrawler')->once()->andReturn(true);
        });

        $viewer = Container::getInstance()->make(Viewer::class);

        $this->assertTrue($viewer->isCrawler());
    }
}
