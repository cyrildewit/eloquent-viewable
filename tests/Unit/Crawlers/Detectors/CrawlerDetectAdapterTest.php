<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Crawlers\Detectors\CrawlerDetectAdapter;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

beforeEach(function (): void {
    // Construct the library with a bot user agent so a fallback to its own
    // state would show up as a wrong answer below.
    $this->detector = new CrawlerDetectAdapter(new CrawlerDetect([], 'Googlebot/2.1'));
});

it('judges the user agent it is given', function (string $userAgent, bool $expected): void {
    expect($this->detector->isCrawler($userAgent))->toBe($expected);
})->with([
    'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    'curl' => ['curl/8.4.0', true],
    'Chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', false],
]);

it('never treats a missing user agent as a crawler', function (?string $userAgent): void {
    expect($this->detector->isCrawler($userAgent))->toBeFalse();
})->with([
    'null' => [null],
    'empty' => [''],
    'blank' => ['   '],
]);

it('asks the library once for the same user agent in a row', function (): void {
    $library = Mockery::mock(CrawlerDetect::class);
    $library->expects('isCrawler')->with('Googlebot/2.1')->once()->andReturn(true);
    $library->expects('isCrawler')->with('Mozilla/5.0')->twice()->andReturn(false);

    $detector = new CrawlerDetectAdapter($library);

    expect($detector->isCrawler('Mozilla/5.0'))->toBeFalse()
        ->and($detector->isCrawler('Googlebot/2.1'))->toBeTrue()
        ->and($detector->isCrawler('Googlebot/2.1'))->toBeTrue()
        ->and($detector->isCrawler('Mozilla/5.0'))->toBeFalse();
});
