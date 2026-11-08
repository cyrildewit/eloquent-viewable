---
title: "Recording views"
description: "Record a view in the controller method that shows the model."
sidebar:
  order: 1
---

Record a view in the controller method that shows the model:

```php
public function show(Post $post)
{
    views($post)->record();

    return view('post.show', compact('post'));
}
```

`record()` returns `true` when the view was stored or queued and `false` when a guard refused it. The guards are listed
under `recording.guards` in the config. Out of the box they drop crawlers, requests without a user agent, `HEAD`
requests, browser prefetches, bursts of views from one visitor and the addresses in `recording.ignored_ip_addresses`,
and enforce cooldowns. See
[the guards](/eloquent-viewable/extending/guards/) for the ones you can turn on, or add your own.

:::note
Tools like **Postman** are often detected as crawlers, so keep `IgnoreCrawlers` in mind when testing.
:::

## Recording from a route

Or leave the controller alone and add the `views` middleware to the route:

```php
Route::get('/posts/{post}', ShowPost::class)->middleware('views');
```

It records the last route parameter bound to a `Viewable` model, and only for a successful response to a `GET`
request, so a 404, a redirect or a form post records nothing. Name a parameter or a model class to pick another, and
pass options the same way:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

->middleware('views:user')                                    // the {user} parameter
->middleware(RecordViews::using(Post::class))                 // every parameter bound to a Post
->middleware(RecordViews::using(['user', 'post']))            // both
->middleware(RecordViews::using('post', collection: 'amp', cooldown: 30, queue: true))
```

A parameter that does not resolve to a `Viewable` throws `InvalidViewable` on the first request. A failed write is
reported and the page is still sent. For a condition, a `viewedBy()` or a `context()`, call `views()` in the controller.

:::tip
Inertia and Livewire reload a page with another `GET` to the same route. A `cooldown` keeps those reloads from
adding views.
:::

## Recording from the browser

A page served from a full-page cache, such as `spatie/laravel-responsecache`, Cloudflare or Varnish, never reaches your
controller, so neither `record()` nor the middleware runs. Turn on the beacon and let the browser record the view:

```php
// config/eloquent-viewable.php
'recording' => [
    'beacon' => [
        'enabled' => true,
    ],
],
```

```blade
@viewsBeacon($post)
@viewsBeacon($post, collection: 'amp', cooldown: 30, queue: true)
```

Once the page has loaded, or a prerendered page is shown, its script posts to a signed URL and the view passes the same
guards as `record()`. The URL is the same for every visitor, never expires and is signed without the host, so it is safe
to cache with the page and behind a proxy.

- **Same origin only.** The route runs the `web` group, so cooldowns, the visitor cookie and the signed-in viewer work
  as usual, and the cookie is set on the beacon's response rather than the cached page. Laravel's CSRF check accepts
  the beacon through the browser's `Sec-Fetch-Site` header and refuses posts from other sites. Browsers that do not
  send it, such as Safari before 16.4, are not counted.
- **A neutral path.** The route lives under `/_ev`, because privacy filter lists block paths such as `/beacon` or
  `/track`. Change `prefix` if it collides with a route of your own, and `middleware` to run other middleware.
- **Quiet answers.** A changed URL gets a `403`, a deleted model a `404` and everything else a `204`, also when a guard
  skips the view.
- **Live counts.** `@viewsBeacon($post, live: true)` also keeps the visitor active while the page is open, see
  [who is looking right now](/eloquent-viewable/rankings/presence/).
- **Your own script.** For a single-page app, build the URL with
  `app(\CyrildeWit\EloquentViewable\Http\Beacon::class)->url($post, collection: 'amp')`.
- **Morph map.** The URL names the model by its morph class. Call `Relation::enforceMorphMap()` to show `post` instead
  of `App\Models\Post`.

## Finding out why a view was not recorded

`attempt()` records like `record()` but returns a `Recording\Data\RecordResult`:

```php
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;

$result = views($post)->attempt();

$result->recorded;   // true when the view was stored or queued
$result->queued;     // true when the write was handed to the queue
$result->skippedBy;  // the guard that refused the view, or null

$result->wasSkippedBy(EnforceCooldown::class);
```

Each refusal also dispatches `Recording\Events\ViewSkipped` with the attempt and the guard, so you can log skipped views
without touching the code that records them.

Every attempt, stored, queued or skipped, also dispatches `Recording\Events\ViewAttempted` with the attempt and its
`RecordResult`. It fires in the request that made the attempt, also when the write is queued. The package only builds
the event when something listens to it, so it costs nothing otherwise.

## Seeing it in Debugbar and Telescope

With [Laravel Debugbar](https://github.com/fruitcake/laravel-debugbar) 4.4 or newer installed, a **Viewable** tab lists
every view of the request: the ones stored, the ones queued and the ones skipped, with the guard that refused each,
the collection, the viewer and the context. Nothing to set up: the tab appears whenever Debugbar is collecting. To
leave it out, turn it off in `config/debugbar.php`:

```php
'collectors' => [
    // ...
    'eloquent_viewable' => false,
],
```

[Laravel Telescope](https://laravel.com/docs/telescope) needs nothing either. Its events watcher records
`ViewAttempted` and `ViewSkipped` like any other event of your application, with the guard named by its class.
