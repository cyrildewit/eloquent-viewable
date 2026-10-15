<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Visitors\Visitor;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;

const VISITOR_COOKIE_NAME = 'eloquent_viewable';

/**
 * @param  array<string, string>  $cookies
 * @param  array<string, string>  $server
 */
function visitorRequest(array $cookies = [], array $server = []): Request
{
    return Request::create('/', 'GET', cookies: $cookies, server: $server);
}

function authWith(?object $user, ?string $guardName = null): AuthFactory
{
    $guard = Mockery::mock(Guard::class);
    $guard->allows('user')->andReturn($user);

    $auth = Mockery::mock(AuthFactory::class);
    $auth->expects('guard')->with($guardName)->andReturn($guard);

    return $auth;
}

beforeEach(function (): void {
    $this->config = new Config(new Repository([
        'eloquent-viewable' => require __DIR__.'/../../../config/eloquent-viewable.php',
    ]));
    $this->cookies = Mockery::mock(QueueingFactory::class);
    $this->cookies->allows('getQueuedCookies')->andReturn([]);
    $this->auth = Mockery::mock(AuthFactory::class);

    $this->visitor = fn (Request $request): Visitor => new Visitor(
        $request,
        $this->config,
        $this->cookies,
        $this->auth,
    );
});

describe('viewer', function (): void {
    it('reports the model signed in on the default guard', function (): void {
        $user = new Post(['id' => 7]);

        $visitor = new Visitor(visitorRequest(), $this->config, $this->cookies, authWith($user));

        expect($visitor->viewer())->toBe($user);
    });

    it('reports no viewer for a guest', function (): void {
        $visitor = new Visitor(visitorRequest(), $this->config, $this->cookies, authWith(null));

        expect($visitor->viewer())->toBeNull();
    });

    it('asks the configured guard', function (): void {
        $config = new Config(new Repository([
            'eloquent-viewable' => ['recording' => ['viewer' => ['guard' => 'api']]],
        ]));
        $user = new Post(['id' => 7]);

        $visitor = new Visitor(visitorRequest(), $config, $this->cookies, authWith($user, 'api'));

        expect($visitor->viewer())->toBe($user);
    });

    it('reports no viewer when the signed-in user is not an Eloquent model', function (): void {
        $visitor = new Visitor(visitorRequest(), $this->config, $this->cookies, authWith(Mockery::mock(Authenticatable::class)));

        expect($visitor->viewer())->toBeNull();
    });
});

it('can get the ip address from the request', function (): void {
    $visitor = ($this->visitor)(visitorRequest(server: ['REMOTE_ADDR' => '241.224.55.106']));

    expect($visitor->ip())->toBe('241.224.55.106');
});

it('reports the user agent from the request', function (): void {
    $visitor = ($this->visitor)(visitorRequest(server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0']));

    expect($visitor->userAgent())->toBe('Mozilla/5.0 Chrome/120.0');
});

it('appends the device headers a proxy adds to the user agent', function (): void {
    $visitor = ($this->visitor)(visitorRequest(server: [
        'HTTP_USER_AGENT' => 'Opera/9.80 (J2ME/MIDP; Opera Mini/9.80)',
        'HTTP_X_OPERAMINI_PHONE_UA' => ' Mozilla/5.0 (Linux; Android 10) ',
        'HTTP_X_SCANNER' => 'Netsparker',
    ]));

    expect($visitor->userAgent())->toBe('Opera/9.80 (J2ME/MIDP; Opera Mini/9.80) Mozilla/5.0 (Linux; Android 10) Netsparker');
});

it('reports no user agent when the request carries none', function (): void {
    // Request::create() adds a `User-Agent: Symfony` header of its own.
    $request = visitorRequest();
    $request->headers->remove('User-Agent');

    expect(($this->visitor)($request)->userAgent())->toBeNull();
});

it('reports no user agent when the header is blank', function (): void {
    $visitor = ($this->visitor)(visitorRequest(server: ['HTTP_USER_AGENT' => '   ']));

    expect($visitor->userAgent())->toBeNull();
});

it('can determine if the visitor has a do not track header from the request', function (bool $expected, array $server): void {
    $visitor = ($this->visitor)(visitorRequest(server: $server));

    expect($visitor->hasDoNotTrackHeader())->toBe($expected);
})->with([
    'header set to 1' => [true, ['HTTP_DNT' => '1']],
    'header set to 0' => [false, ['HTTP_DNT' => '0']],
    'header absent' => [false, []],
]);

it('can determine if the visitor sends the global privacy control signal', function (bool $expected, array $server): void {
    $visitor = ($this->visitor)(visitorRequest(server: $server));

    expect($visitor->hasGlobalPrivacyControl())->toBe($expected);
})->with([
    'header set to 1' => [true, ['HTTP_SEC_GPC' => '1']],
    'header set to 0' => [false, ['HTTP_SEC_GPC' => '0']],
    'header absent' => [false, []],
]);

it('returns the existing visitor id from the cookie', function (): void {
    $visitor = ($this->visitor)(visitorRequest(cookies: [VISITOR_COOKIE_NAME => 'existing-visitor-id']));

    expect($visitor->id())->toBe('existing-visitor-id');
});

it('generates a visitor id and queues it as a cookie when none exists', function (): void {
    $queued = null;

    $this->cookies->expects('queue')
        ->withArgs(function (string $key, string $value, int $minutes) use (&$queued): bool {
            $queued = $value;

            return $key === VISITOR_COOKIE_NAME && $minutes === 2628000;
        });

    $id = ($this->visitor)(visitorRequest())->id();

    expect($id)->toHaveLength(80)
        ->and($queued)->toBe($id);
});

it('hands out the id queued earlier in the request', function (): void {
    $cookies = new CookieJar;
    $cookies->queue('another-cookie', 'value');

    $first = new Visitor(visitorRequest(), $this->config, $cookies, $this->auth)->id();

    expect(new Visitor(visitorRequest(), $this->config, $cookies, $this->auth)->id())->toBe($first)
        ->and($cookies->getQueuedCookies())->toHaveCount(2);
});

it('prefers the cookie the request carries over a queued one', function (): void {
    $cookies = new CookieJar;
    $cookies->queue(VISITOR_COOKIE_NAME, 'queued');

    expect(new Visitor(visitorRequest(cookies: [VISITOR_COOKIE_NAME => 'sent']), $this->config, $cookies, $this->auth)->id())->toBe('sent');
});

it('queues the cookie with the configured name and lifetime', function (): void {
    $config = new Config(new Repository([
        'eloquent-viewable' => ['visitor' => ['cookie' => ['name' => 'who', 'lifetime' => 60]]],
    ]));

    $this->cookies->expects('queue')->withArgs(fn (string $key, string $value, int $minutes): bool => $key === 'who' && $minutes === 60);

    $visitor = new Visitor(visitorRequest(cookies: ['who' => 'known']), $config, $this->cookies, $this->auth);

    expect($visitor->id())->toBe('known');

    new Visitor(visitorRequest(), $config, $this->cookies, $this->auth)->id();
});

it('can determine if the browser only prefetches the page', function (bool $expected, array $server): void {
    $visitor = ($this->visitor)(visitorRequest(server: $server));

    expect($visitor->isPrefetch())->toBe($expected);
})->with([
    'Sec-Purpose prefetch' => [true, ['HTTP_SEC_PURPOSE' => 'prefetch']],
    'Sec-Purpose prerender' => [true, ['HTTP_SEC_PURPOSE' => 'prefetch;prerender']],
    'Purpose prefetch' => [true, ['HTTP_PURPOSE' => 'Prefetch']],
    'X-Moz prefetch' => [true, ['HTTP_X_MOZ' => 'prefetch']],
    'another purpose' => [false, ['HTTP_SEC_PURPOSE' => 'navigate']],
    'header absent' => [false, []],
]);

it('can determine if the request is a HEAD request', function (string $method, bool $expected): void {
    $visitor = ($this->visitor)(Request::create('/', $method));

    expect($visitor->isHeadRequest())->toBe($expected);
})->with([
    'HEAD' => ['HEAD', true],
    'GET' => ['GET', false],
    'POST' => ['POST', false],
]);
