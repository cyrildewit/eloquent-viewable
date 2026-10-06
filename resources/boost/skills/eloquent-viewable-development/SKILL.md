---
name: eloquent-viewable-development
description: Record, count, chart and rank views of Eloquent models with cyrildewit/eloquent-viewable, including periods, unique visitors, cooldowns, trending and "also viewed" rankings, view-count scopes, caching, erasing one person's views and testing with Views::fake().
license: MIT
metadata:
  author: cyrildewit
---

# Eloquent Viewable Development

## When to Activate

- Code calls `views()`, the `Views` facade, `Period` or `Granularity`, or uses the `views` middleware or `@viewsBeacon`.
- A model uses `InteractsWithViews` or `HasViewHistory`, or a query uses `orderByViews()`, `withViewsCount()`, `orderByTrending()` or `whereViewedBy()`.
- A test asserts on recorded views.
- A user is deleted, or a GDPR request asks to erase or export someone's data.

Everything lives under `CyrildeWit\EloquentViewable\`. Read the package README in `vendor/cyrildewit/eloquent-viewable` for the trade-offs behind each config key.

## Model Setup

```php
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class Post extends Model implements Viewable
{
    use InteractsWithViews;
}
```

## Recording

Prefer the route middleware. It records only a successful `GET` response:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

Route::get('/posts/{post}', ShowPost::class)->middleware('views');
Route::get('/posts/{post}', ShowPost::class)->middleware(RecordViews::using('post', collection: 'amp', cooldown: 30));
```

Call `views()` in the controller when you need a condition, a viewer or context:

```php
views($post)->record();                         // false when a guard refused the view
views($post)->cooldown(30)->queue()->record();  // once per visitor per 30 minutes, written by a worker
views($post)->collection('sidebar')->viewedBy($user)->context(['source' => 'feed'])->record();
views($post)->attempt()->skippedBy;             // the guard that refused it, or null
```

`IgnoreBursts` is on by default and refuses one visitor recording more than `recording.bursts.max` different models within `recording.bursts.seconds`. Never record views in a loop of `record()` for an import or a seeder; create them with `View::factory()`.

Pages served from a full-page cache never reach PHP. Enable `recording.beacon.enabled` and print `@viewsBeacon($post)` instead.

## Counting

```php
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;

views($post)->count();
views($post)->unique()->period(Period::pastDays(7))->count();
views(Post::class)->count();                                     // every post
views($post)->period(Period::pastDays(7))->compare();            // current, previous, delta, percent
views($post)->period(Period::pastDays(30))->countByInterval(Granularity::Day); // zero-filled, JSON-ready
Views::forViewables($posts)->counts();                           // one query for a page of models, keyed by id
```

Periods: `Period::create($start, $end)`, `since()`, `upto()`, `pastDays()`, `subHours()` and the like, and `Period::parse('7d')` for URL input. `Period` also binds as a route parameter.

## Ranking and Scopes

```php
views(Post::class)->period(Period::pastDays(7))->top(10);    // most viewed posts
Views::top(10);                                               // across every model type
views(Post::class)->trending(10);                             // recent views weigh more
views($post)->alsoViewed(5, among: Post::class);              // what this post's visitors also viewed

Post::orderByViews('desc', Period::pastDays(7))->paginate();
Post::withViewsCount()->get();                                // adds views_count
Post::orderByTrending()->paginate();
Post::whereNotViewedBy($user)->get();
```

A ranking yields entries with `rank`, `count` and `viewable`. Show `count` to users, never the trending `score`.

## Caching

```php
views($post)->remember(600)->count();   // seconds, or a DateTimeInterface
views($post)->forgetCache();
```

Always give `remember()` a lifetime. Recording a view does not refresh a remembered count.

## Deleting a User or a GDPR Request

```php
$user->forgetViewHistory();                         // delete their views, buffered ones too
$user->forgetViewHistory(includeGuestViews: true);  // also the guest views of the browsers they signed in on
$user->anonymiseViewHistory();                      // keep the counts, unlink the views
$user->exportViewHistory();                         // a lazy collection for a data access request
```

The model needs `Concerns\HasViewHistory`. Call these before deleting the user; once it is gone, run `php artisan views:forget-viewer "App\Models\User" 42`. A guest is erased with `php artisan views:forget-visitor <visitor-id>`. Never set `viewer_type` and `viewer_id` to null yourself: the `visitor` column still identifies them. Listen for `Erasure\Events\ViewHistoryForgotten` to write an audit log.

## When the App Grows

Suggest these only once the problem shows up. Each is a config change, and querying stays the same.

- Repeated counts slow a page: `remember($lifetime)` on the read.
- The insert slows requests: `recording.queue.enabled`, or `recording.store.driver` set to `redis` with `views:flush` scheduled. Never both.
- Sorting long lists by views is slow: `querying.counters` columns on the model's own table, kept current by `views:maintain`.
- `unique()`, whole-type or `alsoViewed()` queries are slow: the optional indexes under "Database indexes" in the README.
- The views table keeps growing: `retention.anonymise` and `retention.prune` with `views:maintain` scheduled, plus `retention.rollups` and `querying.source.driver` set to `rollup` to keep history.
- A `views:maintain` run outlasts its schedule, such as the first run on a large table: add `--max-seconds`, and the next run carries on. On a host that cuts commands off, schedule `Maintenance\Jobs\MaintainViewsJob` instead.
- Bots that pass for a browser inflate counts: `views:purge-bots --dry-run`, then `views:purge-bots`, which deletes only the views inside a burst.
- Cooldowns on stateless API routes: `cooldown.store` set to `cache`.
- No visitor cookie wanted: `visitor.identity` set to `fingerprint`. To count a signed-in user once across devices: `viewer`.
- Opt-in guards in `recording.guards`: `ThrottleVisitors` for a per-visitor rate limit, `IgnoreDoNotTrack` and `IgnoreGlobalPrivacyControl`. Write your own with `Recording\Contracts\RecordingGuard`.

## Testing

```php
use CyrildeWit\EloquentViewable\Facades\Views;

$fake = Views::fake();

$this->get(route('posts.show', $post));

$fake->assertRecorded($post, 1);
$fake->assertNotRecorded($otherPost);
```

The fake serves counts and rankings, but the SQL scopes throw `UnsupportedBySource`. Use `View::factory()->for($post, 'viewable')->create()` for tests that need real rows.

## Avoid

- Writing `View::create()` or incrementing a counter column yourself.
- Reusing state across facade calls. Every `Views::` call starts a fresh builder, so keep a chain on one line or hold it in a variable.
- Pre-v9 class paths. Use `Concerns\InteractsWithViews`, `Models\View`, `Facades\Views` and `Recording\Events\ViewRecorded`, not the old root-namespace classes or `ViewsFacade`.
- The removed `$removeViewsOnDelete` property. Override `shouldRemoveViewsOnDelete(): bool` instead.
