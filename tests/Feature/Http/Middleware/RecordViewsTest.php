<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Recording\Exceptions\RecordingFailed;
use CyrildeWit\EloquentViewable\Recording\Jobs\RecordViewJob;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Apartment;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\Post;
use CyrildeWit\EloquentViewable\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->post = Post::factory()->create();

    // Explicit bindings, so a route action need not type-hint every model.
    Route::model('post', Post::class);
    Route::model('other', Post::class);
    Route::model('apartment', Apartment::class);
    Route::model('user', User::class);
});

/**
 * A test route with the bindings the `web` group would substitute, and the
 * given `views` middleware.
 */
function viewsRoute(string $uri, string $middleware = 'views', ?Closure $action = null, string $method = 'get'): void
{
    Route::middleware([SubstituteBindings::class, $middleware])
        ->{$method}($uri, $action ?? fn (): string => 'ok');
}

it('records a view of the bound model', function (): void {
    viewsRoute('/posts/{post}', action: fn (Post $post): string => 'ok');

    $this->get("/posts/{$this->post->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1);
});

it('records whichever side of the bindings it runs on', function (): void {
    Route::middleware(['views', SubstituteBindings::class])
        ->get('/posts/{post}', fn (Post $post): string => 'ok');

    $this->get("/posts/{$this->post->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1);
});

it('records nothing for a response that is not successful', function (Closure $action): void {
    viewsRoute('/posts/{post}', action: $action);

    $this->get("/posts/{$this->post->getKey()}");

    expect(View::count())->toBe(0);
})->with([
    'not found' => [fn (Post $post) => abort(404)],
    'redirect' => [fn (Post $post): Redirector|\Illuminate\Http\RedirectResponse => redirect('/')],
    'server error' => [fn (Post $post) => abort(500)],
]);

it('records nothing for a model that is not found', function (): void {
    viewsRoute('/posts/{post}');

    $this->get('/posts/'.($this->post->getKey() + 1000))->assertNotFound();

    expect(View::count())->toBe(0);
});

it('records nothing for a request other than GET', function (string $route, string $request): void {
    viewsRoute('/posts/{post}', method: $route);

    $this->call($request, "/posts/{$this->post->getKey()}")->assertOk();

    expect(View::count())->toBe(0);
})->with([
    'POST' => ['post', 'POST'],
    'PUT' => ['put', 'PUT'],
    // A GET route answers HEAD as well.
    'HEAD' => ['get', 'HEAD'],
]);

it('records the last bound viewable of a nested route', function (): void {
    $apartment = Apartment::factory()->create();
    viewsRoute('/apartments/{apartment}/posts/{post}');

    $this->get("/apartments/{$apartment->getKey()}/posts/{$this->post->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1)
        ->and($apartment)->toHaveViewsCount(0);
});

it('skips a bound model that is not viewable', function (): void {
    $user = User::factory()->create();
    viewsRoute('/posts/{post}/by/{user}');

    $this->get("/posts/{$this->post->getKey()}/by/{$user->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1);
});

it('records the model named by its route parameter', function (): void {
    $apartment = Apartment::factory()->create();
    viewsRoute('/apartments/{apartment}/posts/{post}', 'views:apartment');

    $this->get("/apartments/{$apartment->getKey()}/posts/{$this->post->getKey()}")->assertOk();

    expect($apartment)->toHaveViewsCount(1)
        ->and($this->post)->toHaveViewsCount(0);
});

it('records every bound model of the named class', function (): void {
    $other = Post::factory()->create();
    $apartment = Apartment::factory()->create();
    viewsRoute('/compare/{post}/{other}/{apartment}', RecordViews::using(Post::class));

    $this->get("/compare/{$this->post->getKey()}/{$other->getKey()}/{$apartment->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1)
        ->and($other)->toHaveViewsCount(1)
        ->and($apartment)->toHaveViewsCount(0);
});

it('records every model it is told to', function (): void {
    $apartment = Apartment::factory()->create();
    viewsRoute('/apartments/{apartment}/posts/{post}', RecordViews::using(['apartment', 'post']));

    $this->get("/apartments/{$apartment->getKey()}/posts/{$this->post->getKey()}")->assertOk();

    expect($apartment)->toHaveViewsCount(1)
        ->and($this->post)->toHaveViewsCount(1);
});

it('records into the given collection', function (): void {
    viewsRoute('/posts/{post}', RecordViews::using(collection: 'amp'));

    $this->get("/posts/{$this->post->getKey()}")->assertOk();

    expect(View::sole()->collection)->toBe('amp');
});

it('applies the given cooldown', function (): void {
    viewsRoute('/posts/{post}', RecordViews::using(cooldown: 30));

    $this->get("/posts/{$this->post->getKey()}")->assertOk();
    $this->get("/posts/{$this->post->getKey()}")->assertOk();

    expect($this->post)->toHaveViewsCount(1);
});

it('queues the view when told to', function (bool $queue): void {
    Bus::fake();
    viewsRoute('/posts/{post}', RecordViews::using(queue: $queue));

    $this->get("/posts/{$this->post->getKey()}")->assertOk();

    Bus::assertDispatchedTimes(RecordViewJob::class, $queue ? 1 : 0);
})->with([true, false]);

it('reports a view it fails to record and still responds', function (): void {
    Exceptions::fake();
    Route::bind('draft', fn (): Post => new Post);
    viewsRoute('/drafts/{draft}', RecordViews::using(cooldown: 10));

    $this->get('/drafts/1')->assertOk();

    Exceptions::assertReported(RecordingFailed::class);
});

it('rejects a route that binds no viewable', function (): void {
    $this->withoutExceptionHandling();
    viewsRoute('/about');

    expect(fn () => $this->get('/about'))
        ->toThrow(InvalidViewable::class, 'The route [about] binds no viewable model to record a view of.');
});

it('rejects a selector the route does not bind', function (string $selector): void {
    $this->withoutExceptionHandling();
    viewsRoute('/posts/{post}', "views:{$selector}");

    expect(fn () => $this->get("/posts/{$this->post->getKey()}"))
        ->toThrow(InvalidViewable::class, "The route [posts/{post}] binds no viewable [{$selector}] to record a view of.");
})->with(['article', Apartment::class]);

it('rejects a route parameter that is not a viewable', function (): void {
    $this->withoutExceptionHandling();
    $user = User::factory()->create();
    viewsRoute('/users/{user}', 'views:user');

    expect(fn () => $this->get("/users/{$user->getKey()}"))
        ->toThrow(InvalidViewable::class, 'The parameter [user] of the route [users/{user}] must be bound to a model that implements');
});

it('rejects an option it does not understand', function (string $option): void {
    $this->withoutExceptionHandling();
    viewsRoute('/posts/{post}', "views:{$option}");

    expect(fn () => $this->get("/posts/{$this->post->getKey()}"))
        ->toThrow(InvalidConfiguration::class, "The `views` middleware does not understand `{$option}`.");
})->with(['colour=red', 'cooldown=soon', 'cooldown=0', 'queue=maybe']);

it('passes a request without a route through', function (): void {
    $middleware = $this->app->make(RecordViews::class);

    $response = $middleware->handle(Request::create('/'), fn (): Response => new Response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(View::count())->toBe(0);
});
