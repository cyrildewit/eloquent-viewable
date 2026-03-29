<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests;

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Visitor;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

final class VisitorTest extends TestCase
{
    #[Test]
    public function it_can_get_the_ip_address_from_the_request(): void
    {
        $this->mock(Request::class, function ($mock): void {
            $mock->shouldReceive('ip')->once()->andReturn('241.224.55.106');
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertEquals('241.224.55.106', $visitor->ip());
    }

    #[Test]
    public function it_can_determine_if_the_visitor_has_a_do_not_tracker_header_from_the_request(): void
    {
        $this->mock(Request::class, function ($mock): void {
            $mock->shouldReceive('header')->once()->andReturn('1');
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertTrue($visitor->hasDoNotTrackHeader());
    }

    #[Test]
    public function it_can_determine_if_the_visitor_is_a_crawler_from_the_crawler_detector(): void
    {
        $this->mock(CrawlerDetector::class, function ($mock): void {
            $mock->shouldReceive('isCrawler')->once()->andReturn(true);
        });

        $visitor = Container::getInstance()->make(Visitor::class);

        $this->assertTrue($visitor->isCrawler());
    }
}
