<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Visitor;
use Illuminate\Http\Request;

it('can get the ip address from the request', function (): void {
    $this->mock(Request::class, function ($mock): void {
        $mock->shouldReceive('ip')->once()->andReturn('241.224.55.106');
    });

    $visitor = $this->app->make(Visitor::class);

    expect($visitor->ip())->toBe('241.224.55.106');
});

it('can determine if the visitor has a do not tracker header from the request', function (): void {
    $this->mock(Request::class, function ($mock): void {
        $mock->shouldReceive('header')->once()->andReturn('1');
    });

    $visitor = $this->app->make(Visitor::class);

    expect($visitor->hasDoNotTrackHeader())->toBeTrue();
});

it('can determine if the visitor is a crawler from the crawler detector', function (): void {
    $this->mock(CrawlerDetector::class, function ($mock): void {
        $mock->shouldReceive('isCrawler')->once()->andReturn(true);
    });

    $visitor = $this->app->make(Visitor::class);

    expect($visitor->isCrawler())->toBeTrue();
});
