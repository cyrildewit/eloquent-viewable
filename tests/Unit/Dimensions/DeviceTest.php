<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Crawlers\Detectors\CrawlerDetectAdapter;
use CyrildeWit\EloquentViewable\Dimensions\Device;
use CyrildeWit\EloquentViewable\Dimensions\DimensionInput;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

it('names the kind of device', function (?string $userAgent, ?string $expected): void {
    $device = new Device(new CrawlerDetectAdapter(new CrawlerDetect));

    expect($device->resolve(DimensionInput::fake(userAgent: $userAgent)))->toBe($expected);
})->with([
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'mobile'],
    'Android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36', 'mobile'],
    'Samsung Internet' => ['Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/24.0 Chrome/117.0.0.0 Mobile Safari/537.36', 'mobile'],
    'Opera Mini' => ['Opera/9.80 (Android; Opera Mini/36.2.2254/119.132; U; id) Presto/2.12.423 Version/12.16', 'mobile'],
    'Windows Phone' => ['Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15063', 'mobile'],
    'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1', 'tablet'],
    'Android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', 'tablet'],
    'Kindle Fire' => ['Mozilla/5.0 (Linux; Android 9; KFTRWI) AppleWebKit/537.36 (KHTML, like Gecko) Silk/124.3.1 like Chrome/124.0.6367.111 Safari/537.36', 'tablet'],
    'Chrome on a Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36', 'desktop'],
    'Firefox on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0', 'desktop'],
    'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Edg/124.0.2478.80', 'desktop'],
    'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'bot'],
    'Googlebot on a phone' => ['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.118 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'bot'],
    'curl' => ['curl/8.4.0', 'bot'],
    'no user agent' => [null, null],
]);

it('judges each user agent once', function (): void {
    $crawlers = Mockery::mock(CrawlerDetector::class);
    $crawlers->expects('isCrawler')->with('Mozilla/5.0')->once()->andReturn(false);

    $device = new Device($crawlers);

    expect($device->resolve(DimensionInput::fake(userAgent: 'Mozilla/5.0')))->toBe('desktop')
        ->and($device->resolve(DimensionInput::fake(userAgent: 'Mozilla/5.0')))->toBe('desktop');
});

it('forgets what it judged once it has judged many user agents', function (): void {
    $crawlers = Mockery::mock(CrawlerDetector::class);
    $crawlers->expects('isCrawler')->with('Mozilla/5.0 0')->twice()->andReturn(false);
    $crawlers->allows('isCrawler')->andReturn(false);

    $device = new Device($crawlers);

    foreach (range(0, 64) as $index) {
        $device->resolve(DimensionInput::fake(userAgent: "Mozilla/5.0 {$index}"));
    }

    $device->resolve(DimensionInput::fake(userAgent: 'Mozilla/5.0 0'));
});
