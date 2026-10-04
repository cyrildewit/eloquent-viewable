<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Octane\Testing\Fakes\FakeClient;
use Laravel\Octane\Testing\Fakes\FakeWorker;
use Symfony\Component\HttpFoundation\Response;

/*
 * Every test here boots the application once as an Octane worker and sends
 * several requests through it, the way a long-lived Swoole, RoadRunner or
 * FrankenPHP worker does. Octane clones the booted application for every
 * request, so anything the package resolved during boot, or stores in a
 * static, is shared by the requests that follow.
 */

beforeEach(function (): void {
    $this->app->register(OctaneServiceProvider::class);

    $this->post = Post::factory()->create();

    Route::model('post', Post::class);
    Route::middleware(['web', 'views'])->get('/posts/{post}', fn (Post $post): string => 'ok');
});

/**
 * Sends the requests through one worker and hands back the client that
 * collected the responses.
 *
 * @param  list<Request>  $requests
 */
function octane(Application $app, array $requests): FakeClient
{
    $factory = new class($app) extends ApplicationFactory
    {
        public function __construct(private readonly Application $app)
        {
            parent::__construct($app->basePath());
        }

        #[Override]
        public function createApplication(array $initialInstances = []): Application
        {
            foreach ($initialInstances as $key => $value) {
                $this->app->instance($key, $value);
            }

            return $this->warm($this->app);
        }
    };

    $worker = new FakeWorker($factory, $client = new FakeClient($requests));
    $worker->boot();
    $worker->run();

    // Octane points the framework's managers at each request's sandbox and
    // only moves them on when the next request arrives. Handing them the base
    // application lets the test assert against the database afterwards.
    $app->make('events')->dispatch(new RequestReceived($app, $app, Request::create('/')));

    expect($client->errors)->toBe([]);

    return $client;
}

/** @param  array<string, string>  $headers */
function octaneRequest(string $uri, array $headers = []): Request
{
    $request = Request::create($uri);
    $request->headers->add($headers);

    return $request;
}

it('gives every visitor without a cookie an id of their own', function (): void {
    $client = octane($this->app, [
        octaneRequest("/posts/{$this->post->getKey()}"),
        octaneRequest("/posts/{$this->post->getKey()}"),
    ]);

    expect($client->responses)->each->toBeInstanceOf(Response::class)
        ->and(View::query()->pluck('visitor')->unique())->toHaveCount(2);
});

it('does not carry a cooldown kept in the session over to the next session', function (): void {
    // Without cookie encryption, so both requests can send the same visitor
    // cookie. The cooldown key is the visitor, so only the session differs.
    Route::middleware([StartSession::class, AddQueuedCookiesToResponse::class, SubstituteBindings::class, 'views:cooldown=60'])
        ->get('/cooled/{post}', fn (Post $post): string => 'ok');

    $visitor = fn (): Request => Request::create("/cooled/{$this->post->getKey()}", cookies: ['eloquent_viewable' => 'same-visitor']);

    octane($this->app, [$visitor(), $visitor()]);

    expect($this->post)->toHaveViewsCount(2)
        ->and(View::query()->pluck('visitor')->unique()->all())->toBe(['same-visitor']);
});

it('does not carry the viewer over to the next request', function (): void {
    config(['eloquent-viewable.recording.viewer.enabled' => true]);
    $user = User::factory()->create();

    Route::middleware(['web', 'views'])->get('/signed-in/{post}', function (Post $post) use ($user): string {
        Auth::setUser($user);

        return 'ok';
    });

    octane($this->app, [
        octaneRequest("/signed-in/{$this->post->getKey()}"),
        octaneRequest("/posts/{$this->post->getKey()}"),
    ]);

    expect(View::query()->orderBy('id')->pluck('viewer_id')->all())->toBe([$user->getKey(), null]);
});

it('judges every request by its own user agent', function (array $agents, int $recorded): void {
    octane($this->app, array_map(
        fn (string $agent): Request => octaneRequest("/posts/{$this->post->getKey()}", ['User-Agent' => $agent]),
        $agents,
    ));

    expect($this->post)->toHaveViewsCount($recorded);
})->with([
    'crawler first' => [['Googlebot/2.1 (+http://www.google.com/bot.html)', 'Mozilla/5.0 (Macintosh) Firefox/130.0'], 1],
    'browser first' => [['Mozilla/5.0 (Macintosh) Firefox/130.0', 'Googlebot/2.1 (+http://www.google.com/bot.html)'], 1],
]);

it('reads config changed during a request', function (): void {
    Route::middleware(['web', 'views'])->get('/renamed/{post}', function (Post $post): string {
        config(['eloquent-viewable.visitor.cookie.name' => 'renamed_visitor']);

        return 'ok';
    });

    $client = octane($this->app, [octaneRequest("/renamed/{$this->post->getKey()}")]);

    $cookies = array_map(fn ($cookie): string => $cookie->getName(), $client->responses[0]->headers->getCookies());

    expect($cookies)->toContain('renamed_visitor');
});

it('does not leave package services behind in the worker', function (): void {
    Route::middleware(['web'])->get('/destroy/{post}', function (Post $post): string {
        views($post)->record();
        views($post)->destroy();

        return 'ok';
    });

    $before = array_keys((fn (): array => $this->instances)->call($this->app));

    octane($this->app, [octaneRequest("/destroy/{$this->post->getKey()}")]);

    $leaked = array_filter(
        array_diff(array_keys((fn (): array => $this->instances)->call($this->app)), $before),
        fn (string $abstract): bool => str_starts_with($abstract, 'CyrildeWit\\'),
    );

    expect(array_values($leaked))->toBeEmpty();
});
