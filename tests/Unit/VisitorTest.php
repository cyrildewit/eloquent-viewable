<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\CrawlerDetector;
use CyrildeWit\EloquentViewable\Visitor;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Http\Request;

const VISITOR_COOKIE_KEY = 'eloquent_viewable';

/**
 * @param  array<string, string>  $cookies
 * @param  array<string, string>  $server
 */
function visitorRequest(array $cookies = [], array $server = []): Request
{
    return Request::create('/', 'GET', cookies: $cookies, server: $server);
}

beforeEach(function (): void {
    $this->config = new Repository([
        'eloquent-viewable' => require __DIR__.'/../../config/eloquent-viewable.php',
    ]);
    $this->crawlerDetector = Mockery::mock(CrawlerDetector::class);
    $this->cookies = Mockery::mock(QueueingFactory::class);

    $this->visitor = fn (Request $request): Visitor => new Visitor(
        $request,
        $this->crawlerDetector,
        $this->config,
        $this->cookies,
    );
});

it('can get the ip address from the request', function (): void {
    $visitor = ($this->visitor)(visitorRequest(server: ['REMOTE_ADDR' => '241.224.55.106']));

    expect($visitor->ip())->toBe('241.224.55.106');
});

it('can determine if the visitor has a do not track header from the request', function (bool $expected, array $server): void {
    $visitor = ($this->visitor)(visitorRequest(server: $server));

    expect($visitor->hasDoNotTrackHeader())->toBe($expected);
})->with([
    'header set to 1' => [true, ['HTTP_DNT' => '1']],
    'header set to 0' => [false, ['HTTP_DNT' => '0']],
    'header absent' => [false, []],
]);

it('can determine if the visitor is a crawler from the crawler detector', function (): void {
    $this->crawlerDetector->expects('isCrawler')->andReturn(true);

    $visitor = ($this->visitor)(visitorRequest());

    expect($visitor->isCrawler())->toBeTrue();
});

it('returns the existing visitor id from the cookie', function (): void {
    $visitor = ($this->visitor)(visitorRequest(cookies: [VISITOR_COOKIE_KEY => 'existing-visitor-id']));

    expect($visitor->id())->toBe('existing-visitor-id');
});

it('generates a visitor id and queues it as a cookie when none exists', function (): void {
    $queued = null;

    $this->cookies->expects('queue')
        ->withArgs(function (string $key, string $value, int $minutes) use (&$queued): bool {
            $queued = $value;

            return $key === VISITOR_COOKIE_KEY && $minutes === 2628000;
        });

    $id = ($this->visitor)(visitorRequest())->id();

    expect($id)->toHaveLength(80)
        ->and($queued)->toBe($id);
});
