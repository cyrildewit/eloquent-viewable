---
name: eloquent-viewable-development
description: Record, count, chart and rank views of Eloquent models with cyrildewit/eloquent-viewable, including periods, unique visitors, cooldowns, trending, "also viewed" and personal "recommended for you" rankings, view-count scopes, caching, erasing one person's views and testing with Views::fake().
license: MIT
metadata:
  author: cyrildewit
---

# Eloquent Viewable Development

## When to Activate

- Code calls `views()`, the `Views` facade, `Period` or `Granularity`, or uses the `views` middleware or `@viewsBeacon`.
- A model uses `InteractsWithViews` or `HasViewHistory`, or a query uses `orderByViews()`, `withViewsCount()`, `orderByTrending()`, `whereViewedBy()` or `recommendedFor()`.
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
views($post)->period(Period::pastDays(30))->returning()->count(); // visitors who viewed on two days or more
views($post)->period(Period::pastDays(30))->countByFrequency();   // [1 => 820, 2 => 140, '3+' => 60] via toArray()
```

`returning()` only works with `count()` and `compare()`; every other read throws. It counts visitors, so do not add `unique()`. With the `fingerprint` identity every guest counts as new.

Periods: `Period::create($start, $end)`, `since()`, `upto()`, `pastDays()`, `subHours()` and the like, and `Period::parse('7d')` for URL input. `Period` also binds as a route parameter.

## Ranking and Scopes

```php
views(Post::class)->period(Period::pastDays(7))->top(10);    // most viewed posts
Views::top(10);                                               // across every model type
views(Post::class)->trending(10);                             // recent views weigh more
views($post)->alsoViewed(5, among: Post::class);              // what this post's visitors also viewed
$user->recommended(Post::class, limit: 10);                   // for one viewer, needs HasViewHistory
views(Post::class)->recommended(10);                          // for the current visitor

Post::orderByViews('desc', Period::pastDays(7))->paginate();
Post::withViewsCount()->get();                                // adds views_count
Post::orderByTrending()->paginate();
Post::whereNotViewedBy($user)->get();
Post::where('published', true)->recommendedFor($user)->paginate();  // adds recommendation_score
```

A ranking yields entries with `rank`, `count` and `viewable`. Show `count` to users, never the trending `score`.

A recommendation has `rank`, `score`, `viewable` and `because`, the viewer's views it came from. Show `because` as the reason, never the `score`. An empty list is a valid answer below `querying.also_viewed.minimum_visitors`: fall back yourself, such as to `trending()`.

## Who Is Looking Right Now

For "12 people are viewing this", use presence, never a count of recent rows. Enable `presence.enabled`; the `redis` driver is the one for production.

```php
views($product)->activeVisitors();                // visitors in the last `presence.window` seconds
views(Product::class)->live()->count();           // across the type
Views::live()->top(10);                           // what is being looked at now
Views::forViewables($products)->live()->counts(); // one round trip for a page of models
views($product)->live()->within(60)->count();     // narrow the window, never widen it
```

Print `@viewsBeacon($product, live: true)` so the page keeps its visitor active with a heartbeat and lets them go when it closes. With `presence.expose_count` on, the script fills every `[data-views-live]` element. A cooldown skips the view but keeps the visitor active; every other guard keeps them out. In tests, `Views::fake()->present($product, 12)` puts visitors on a model.

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
- `unique()`, whole-type, `alsoViewed()` or `recommended()` queries are slow: the optional indexes under "Database indexes" in the README.
- `alsoViewed()` or `recommended()` stay slow under heavy traffic: `querying.pairs.enabled`, the `eloquent-viewable-pairs` migration and `views:pairs` scheduled.
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
