<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor;

function crawlerAttempt(?string $userAgent): ViewAttempt
{
    $visitor = Mockery::mock(Visitor::class);
    $visitor->allows('userAgent')->andReturn($userAgent);

    return new ViewAttempt(new Post(['id' => 1]), $visitor);
}

it('asks the detector about the visitor user agent', function (bool $isCrawler): void {
    $detector = Mockery::mock(CrawlerDetector::class);
    $detector->expects('isCrawler')->with('Googlebot/2.1')->andReturn($isCrawler);

    expect(new IgnoreCrawlers($detector)->allows(crawlerAttempt('Googlebot/2.1')))->toBe(! $isCrawler);
})->with([
    'crawler' => [true],
    'browser' => [false],
]);

it('hands a missing user agent to the detector as null', function (): void {
    $detector = Mockery::mock(CrawlerDetector::class);
    $detector->expects('isCrawler')->with(null)->andReturn(false);

    expect(new IgnoreCrawlers($detector)->allows(crawlerAttempt(null)))->toBeTrue();
});
