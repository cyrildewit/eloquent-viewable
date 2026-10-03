# Upgrade Guide

## Table of contents

- [Upgrading from v8.0.0 to v9.0.0](#upgrading-from-v800-to-v900)
- [Upgrading from v7.0.3 to v8.0.0](#upgrading-from-v703-to-v800)

The version upgrade guides for versions below `v7.0.3` are still accessible in the major version branches like [`7.x`](/cyrildewit/eloquent-viewable/blob/7.x/UPGRADING.md).  

## Upgrading from v8.0.0 to v9.0.0

### Periods are half-open

`Period` now includes its start and excludes its end. A view recorded exactly at the end bound no longer counts, so `Period::create('2018-01-01', '2018-02-01')` covers all of January and nothing of February, and `Period::upto($date)` means before `$date`. Counts whose period end lands exactly on a stored `viewed_at` drop by one.

This aligns `Period` with the buckets returned by the new `countByInterval()`, so drilling from a bucket into `count()` gives the same number.

### Period bounds are converted to the application timezone

`viewed_at` is stored as the wall clock of `app.timezone`. `Period` now converts the bounds it is given to that zone, so a bound built in another timezone matches the stored values instead of being compared as-is. `getStartDateTime()` and `getEndDateTime()` return the converted instances. String bounds are unaffected, since they were already parsed in the application timezone.

### Add an index on `viewable_type`, `viewable_id` and `viewed_at`

The `create_views_table` stub now creates a composite index on those three columns. Period counts and `countByInterval()` read only the rows inside the period instead of every view of the model.

Your published migration has already run, so republishing does nothing. Add the index in a migration of your own:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected Builder $schema;

    protected string $table;

    public function __construct()
    {
        $this->schema = Schema::connection(
            config('eloquent-viewable.models.view.connection')
        );

        $this->table = config('eloquent-viewable.models.view.table_name');
    }

    public function up(): void
    {
        $this->schema->table($this->table, function (Blueprint $table) {
            $table->index(['viewable_type', 'viewable_id', 'viewed_at'], "{$this->table}_viewable_viewed_at_index");
        });
    }

    public function down(): void
    {
        $this->schema->table($this->table, function (Blueprint $table) {
            $table->dropIndex("{$this->table}_viewable_viewed_at_index");
        });
    }
};
```

On a large `views` table, building the index locks writes for the duration. MySQL 8 and MariaDB 10.5 do this online, and on Postgres you can swap the `up()` body for `CREATE INDEX CONCURRENTLY` with `public $withinTransaction = false;` on the migration.

### Add the `viewer` and `context` columns

The `create_views_table` stub now creates two nullable columns for the model that was signed in when a view was recorded, `viewer_type` and `viewer_id` with an index, and a nullable `context` JSON column. Every store writes all three, so the columns have to exist even if you never record a viewer or a context. Your published migration has already run, so add them in a migration of your own:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected Builder $schema;

    protected string $table;

    public function __construct()
    {
        $this->schema = Schema::connection(
            config('eloquent-viewable.models.view.connection')
        );

        $this->table = config('eloquent-viewable.models.view.table_name');
    }

    public function up(): void
    {
        $this->schema->table($this->table, function (Blueprint $table) {
            $table->nullableMorphs('viewer');
            $table->json('context')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->table($this->table, function (Blueprint $table) {
            $table->dropMorphs('viewer');
            $table->dropColumn('context');
        });
    }
};
```

Recording the viewer stays off until you set `recording.viewer.enabled`, so nothing changes in what is stored until you opt in. See [Who viewed what](README.md#who-viewed-what).

### The visitor reports the signed-in model

`Visitors\Contracts\Visitor` gained `viewer(): ?Model`, the signed-in Eloquent model or `null` for a guest. A custom visitor has to implement it; returning `null` is correct wherever there is no session to read, and the view is then recorded as a guest view unless `viewedBy()` names a viewer. The shipped `Visitors\Visitor` reads the model from the guard named by `recording.viewer.guard` and takes `Illuminate\Contracts\Auth\Factory` as a fourth constructor argument. A subclass that overrides the constructor passes it on; a class resolved from the container needs no change.

### The `View` and `Views` contracts are gone

`CyrildeWit\EloquentViewable\Contracts\View` and `CyrildeWit\EloquentViewable\Contracts\Views` no longer exist. Type hints, `instanceof` checks and container bindings against them break.

A custom view model now extends `Models\View` and is named in the config file:

```php
'models' => [
    'view' => [
        'class' => \App\Models\View::class,
    ],
],
```

Remove the `$this->app->bind(Contracts\View::class, ...)` call from your service provider. A model that implemented the contract without extending the shipped model has to extend it now. The package throws `InvalidConfiguration` for a class that does not.

A `$table` or `$connection` property on the subclass now takes precedence over `models.view.table_name` and `models.view.connection`. Before, the config value always won, so a subclass that declared one of those properties was ignored. If you had both set to different values, the model's value applies after upgrading. The migration stub keeps reading the two config keys, so a table renamed through the model also needs `table_name` set, or the published migration edited.

The `Views` builder can no longer be replaced through the container. If you bound your own class to `Contracts\Views`, move the behaviour into a macro on `Views`, or bind one of the contracts behind it: `Querying\Contracts\ViewSource`, `Recording\Contracts\RecordsViews` or `Recording\Contracts\ViewStore`, or add a `Recording\Contracts\RecordingGuard` to the `recording.guards` config list. Code that type-hinted `Contracts\Views` type-hints `Views` now; the `views()` helper and the facade return that class.

### Keep views on delete with `shouldRemoveViewsOnDelete()`

The `$removeViewsOnDelete` property is replaced by a method on the `Viewable` contract. The `InteractsWithViews` trait implements it and returns `true`, so override it to keep the views of a deleted model:

```php
// Before
protected $removeViewsOnDelete = false;

// After
public function shouldRemoveViewsOnDelete(): bool
{
    return false;
}
```

The property is no longer read. A `protected` one, as the v8 README showed, was ignored already, so those models kept losing their views; they keep them after this change. A class that implements `Viewable` without the trait has to add the method.

### Recording returns nothing and the event carries a `ViewRecord`

`Recording\Contracts\RecordsViews::handle()` and `Recording\Contracts\ViewStore::store()` return `void`. Both returned the stored `Models\View` before. A custom implementation of either drops its return value. Code that used the return value of `handle()` reads the `ViewRecord` it passed in instead; a store may buffer the write, so there is not always a row to query at that point.

`Recording\Events\ViewRecorded` carries a `Data\ViewRecord` as `$record` instead of a `Models\View` as `$view`. The record has the viewable type and key, the visitor, the collection and `viewed_at`. A listener that read `$event->view->viewable_id` reads `$event->record->viewableId` now. The event is dispatched once the store has accepted the record. With the database store the row exists at that moment; a store that buffers writes lands it later. A listener therefore reads what it needs from the record and does not query the views table for the row.

```php
// before
$event->view->viewable_id;
$event->view->viewable;

// after
$event->record->viewableId;
$event->record->viewableType::find($event->record->viewableId);
```

The event no longer uses `SerializesModels`, so queued listeners receive the record as it was dispatched.

The reason for both changes is that a store may buffer views and write them later. There is no row to return or serialize at the moment of recording.

### Stores write without `View` model events

`Recording\Stores\DatabaseStore` writes through the query builder instead of `Models\View::create()`, so the `creating` and `created` events of the view model are no longer fired when a view is stored. An observer or a listener registered on the view model for those events stops running. Move the logic to a listener on `Recording\Events\ViewRecorded`, which carries the same data as the row.

A custom `Recording\Contracts\ViewStore` implements the new `storeMany(iterable $records)` method. It receives an iterable of `ViewRecord` objects and is expected to write them in as few operations as the store allows. Delegating `store()` to `storeMany([$record])` is enough when the store has no cheaper single write.

### `PendingView` is `Data\ViewRecord`

The value object handed to the record action moved and was renamed. Update the import in any custom `RecordsViews` or `ViewStore` implementation. The constructor and `toArray()` are unchanged.

### Stores are selected through `recording.store.driver`

The container binding for `Recording\Contracts\ViewStore` now resolves through `Recording\Stores\StoreManager`, which reads the new `recording.store.driver` config key. The published config does not have the key; the package default is `database`, so nothing changes until you set it. Add it to your published config if you want it visible:

```php
'recording' => [
    'store' => [
        'driver' => 'database',
    ],
],
```

A direct `$this->app->bind(ViewStore::class, ...)` keeps working and overrides the manager. To make a store selectable by name instead, register it with `StoreManager::extend()`. See the README under [Choosing where views are stored](README.md#choosing-where-views-are-stored).

### Cooldowns are selected through `cooldown.store`

`Cooldowns\CooldownManager` now builds the `Cooldowns\Contracts\CooldownStore` named by the new `cooldown.store` config key, and `EnforceCooldown` depends on that contract. The `session` driver is the default and behaves as in v8. The new `cache` driver keeps cooldowns in the cache store named by `cooldown.cache.store`, so they also work on routes without a session. See the README under [Where cooldowns are kept](README.md#where-cooldowns-are-kept).

A published v8 config has a `cooldown` block without the new keys, and Laravel does not merge nested defaults into it. Add them, otherwise every `views()` call throws `Exceptions\InvalidConfiguration` naming `cooldown.store`:

```php
'cooldown' => [
    'store' => 'session',
    'key' => 'cyrildewit.eloquent-viewable.cooldowns',
    'cache' => [
        'store' => null,
    ],
],
```

`CooldownManager::push()` is gone. Code that called it uses the store instead, with a key from `Cooldowns\Cooldown::of($viewable, $visitorId, $collection)->key()`. The session store keeps cooldowns in a new format, so cooldowns running when you deploy end early, once.

### Every facade call starts a fresh builder

The `Views` facade used to hand back the same builder for the rest of the request, so the viewable, period, collection and every other option of one call leaked into the next: `Views::forViewable($post)->record()` followed by `Views::count()` counted the post. The facade now resolves a fresh builder on every static call, like the `views()` helper always did. Keep a chain on one line, or hold the builder in a variable:

```php
// Before: counted $post through the leaked viewable. Now: throws InvalidViewable.
Views::forViewable($post)->record();
Views::count();

// Either of these.
Views::forViewable($post)->count();

$views = Views::forViewable($post);
$views->record();
$views->count();
```

Doubles are unaffected: `Views::shouldReceive()` and `Views::swap()` still take over every call.

### Counting goes through a source

`Views::count()`, `Views::countByInterval()` and the other counts read through the `Querying\Contracts\ViewSource` bound in the container, and the `withViewsCount()`, `orderByViews()`, `whereViewsCount()` and `whereViewedBy()` scopes read through it when it also implements `Querying\Contracts\SubquerySource`, as the shipped `database` source does. If you replaced the `Views` class to change how counts are computed, implementing that contract is now the smaller change, and it reaches the scopes too. The `querying.source.driver` config key names the source; the package default is `database`, so nothing changes until you set it. See the README under [Customizing how views are counted](README.md#customizing-how-views-are-counted).

The scopes used to call `withAggregate()` on the `views` relation. They now add a correlated subselect from the source. The results are the same, and the count column is still cast to an integer, but code that inspected the generated SQL will see a different shape.

### Recording passes a list of guards, and the list is the only switch

The crawler, Do Not Track, IP address and cooldown checks run as guard classes listed under the new `recording.guards` config key, in that order. A guard runs when it is listed and not otherwise, so the `ignore_bots` and `honor_dnt` keys are gone and which checks run is your choice. The new `IgnoreGlobalPrivacyControl` guard honours the `Sec-GPC: 1` header the same way `IgnoreDoNotTrack` honours `DNT: 1`. To add a check of your own, implement `Recording\Contracts\RecordingGuard` and list the class. See the README under [Adding a recording guard](README.md#adding-a-recording-guard).

The defaults match v8: `IgnoreCrawlers`, `IgnoreIpAddresses` and `EnforceCooldown` are listed, and the Do Not Track check is off. Map your old settings onto the list in your published config:

```php
'recording' => [
    'guards' => [
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers::class,    // remove if you had ignore_bots => false
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses::class,
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack::class,  // add if you had honor_dnt => true
        \CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown::class,
    ],
],
```

A published v8 config has no `recording.guards` key, so the package defaults apply until you add it.

When a guard refuses, the package now dispatches `Recording\Events\ViewSkipped` with the attempt and the guard. `record()` still returns `false` in that case; the event is additive.

`Views` gained `attempt()`, which records like `record()` but returns a `Recording\Data\RecordResult` with `recorded`, `queued` and `skippedBy`, the guard that refused the view or `null`. `record()` keeps returning `bool`, so nothing changes for existing calls. Switch to `attempt()` where you need to know why a view was not recorded:

```php
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;

// Before
if (! views($post)->record()) {
    // skipped, but by what?
}

// After
$result = views($post)->attempt();

if ($result->wasSkippedBy(EnforceCooldown::class)) {
    // ...
}
```

`Recording\Recorder::record()` returns that `RecordResult` instead of `bool`. Only code that calls the recorder directly rather than through `Views` is affected.

The `Views` constructor now takes the visitor, `Recording\Recorder`, `Querying\Reader`, `Recording\Actions\DestroyViews` and `Querying\Cache\CacheVersions`. The config, the cache, the cooldown manager, the bus dispatcher and the count actions are gone from it. Only a subclass that overrides the constructor is affected.

### Prefetched pages are no longer counted

The new `IgnorePrefetch` guard is listed in `recording.guards` by default. It drops a view when the browser only prefetches or prerenders the page, which it marks with a `Sec-Purpose`, `Purpose` or `X-Moz` header holding `prefetch`. Counts on sites that use prefetching or speculation rules go down by the pages nobody opened. A published config that lists its own guards keeps that list; add `\CyrildeWit\EloquentViewable\Recording\Guards\IgnorePrefetch::class` to it to drop prefetches too.

`Visitors\Contracts\Visitor` gained `isPrefetch(): bool`. A custom visitor has to implement it; return `false` where the request carries no such header.

### The visitor reports its user agent, the detector judges it

`Visitors\Contracts\Visitor` lost `isCrawler()` and gained `userAgent(): ?string` and `hasGlobalPrivacyControl(): bool`. The visitor only reports what the request says; the `IgnoreCrawlers` guard hands the user agent to the bound `Crawlers\Contracts\CrawlerDetector`, whose `isCrawler()` now takes that string: `isCrawler(?string $userAgent): bool`. A `null` or empty user agent is never a crawler.

A custom `Visitor` replaces `isCrawler()` with `userAgent()`, returning `null` when it has none, and adds `hasGlobalPrivacyControl()`. A class that extends the shipped `Visitor` and overrides the constructor drops the `CrawlerDetector` argument; the constructor is now `(Request $request, Support\Config $config, QueueingFactory $cookies)`. A custom `CrawlerDetector` adds the `?string $userAgent` parameter and judges that instead of reading the request. Tests that mocked `Visitor::isCrawler()` mock `userAgent()` or bind a detector.

```php
// before
$this->app->bind(CrawlerDetector::class, fn () => new class implements CrawlerDetector {
    public function isCrawler(): bool { return true; }
});

// after
$this->app->bind(CrawlerDetector::class, fn () => new class implements CrawlerDetector {
    public function isCrawler(?string $userAgent): bool { return true; }
});
```

The shipped detector is now a stateless singleton. Before, it captured the headers of the first request it saw, which in a long-running worker such as Octane meant every later request was judged by that first user agent. The shipped `Visitor::userAgent()` joins the `User-Agent` header with the device headers a proxy adds, such as `X-Operamini-Phone-UA`, which is the same list the detector library read itself, so detection results are unchanged.

### The config file is grouped by module

Every top-level key except `models` and `cooldown` moved under a group named after the module that reads it. Publish the config again or move the keys in your published file using the table below. A v8 key left at the top level is ignored and the package default applies, so check `ignored_ip_addresses`, `honor_dnt` and `queue` in particular: left in the old place, they silently fall back to the defaults.

| v8 | v9 |
| --- | --- |
| `store` | `recording.store` |
| `guards` | `recording.guards` |
| `ignored_ip_addresses` | `recording.ignored_ip_addresses` |
| `queue` | `recording.queue` |
| `source` | `querying.source` |
| `cache` | `querying.cache` |
| `max_intervals` | `querying.max_intervals` |
| `visitor_cookie_key` | `visitor.cookie.name` |
| `ignore_bots` | removed; `IgnoreCrawlers` is listed in `recording.guards` by default, remove it to record crawler views |
| `honor_dnt` | removed; list `IgnoreDoNotTrack` in `recording.guards` |
| `cooldown` | gained `cooldown.store` and `cooldown.cache.store`, see [above](#cooldowns-are-selected-through-cooldownstore) |
| `models` | unchanged |

The cookie lifetime, five years before and hard-coded, is now `visitor.cookie.lifetime` in minutes. `Support\Config::visitorCookieKey()` is now `visitorCookieName()`, and `ignoreBots()` and `honorDoNotTrack()` are gone; that class is internal, so only code that reached into it is affected.

The config values are now validated when they are read. A key that names a table, connection, queue or cache store must be a string or `null`, and `recording.ignored_ip_addresses` must hold only strings. Anything else, such as a connection given as an enum, throws `Exceptions\InvalidConfiguration` naming the key.

### `CacheKey` moved and `make()` takes a `ViewsQuery`

`CyrildeWit\EloquentViewable\CacheKey` is now `CyrildeWit\EloquentViewable\Querying\Cache\CacheKey`. Update the import if you build cache keys yourself. `CacheKey::make(?Period $period, bool $unique, ?string $collection)` is now `CacheKey::make(ViewsQuery $query, ?Granularity $granularity = null, ?string $grouping = null, ?int $limit = null)`. The digest also changed, so counts cached by an earlier version are recalculated once after upgrading. No action is needed for that.

### Classes moved into module namespaces

Most classes now live in a namespace named after the module they belong to. The names you are most likely to have imported are the trait, the model and the facade. Search your code for `CyrildeWit\EloquentViewable\` and update each import from the table. Only the namespace changed unless the row says otherwise.

| v8 | v9 |
| --- | --- |
| `InteractsWithViews` | `Concerns\InteractsWithViews` |
| `View` | `Models\View` |
| `ViewsFacade` | `Facades\Views` |
| `Visitor` | `Visitors\Visitor` |
| `Contracts\Visitor` | `Visitors\Contracts\Visitor` |
| `Contracts\CrawlerDetector` | `Crawlers\Contracts\CrawlerDetector` |
| `CrawlerDetectAdapter` | `Crawlers\Detectors\CrawlerDetectAdapter` |
| `CooldownManager` | `Cooldowns\CooldownManager` |
| `PendingView` | `Data\ViewRecord` (renamed) |
| `Events\ViewRecorded` | `Recording\Events\ViewRecorded` |
| `ViewableObserver` | `Recording\Observers\ViewableObserver` |
| `Actions\CreateView` | `Recording\Actions\RecordView` (renamed) |
| `Contracts\CreateView` | `Recording\Contracts\RecordsViews` (renamed) |
| `Jobs\StoreView` | `Recording\Jobs\RecordViewJob` (renamed) |
| `Exceptions\ViewRecordException` | `Recording\Exceptions\RecordingFailed` (renamed) |

`Views`, `Contracts\Viewable`, everything under `Support\`, the root exceptions and the `Querying\` namespace did not move.

If you bound your own implementation of the recording action, bind it to `Recording\Contracts\RecordsViews` now and drop the return value, see [above](#recording-returns-nothing-and-the-event-carries-a-viewrecord). A `catch (ViewRecordException $e)` becomes `catch (RecordingFailed $e)`, and `Bus::assertDispatched(StoreView::class)` in your tests becomes `Bus::assertDispatched(RecordViewJob::class)`.

`Recording\Jobs\RecordViewJob` no longer uses the `Dispatchable` trait, so `RecordViewJob::dispatch()` is gone. The package never called it; if your code did, dispatch an instance with `dispatch(new RecordViewJob($record))` or `Bus::dispatch()` instead.

The published migration file is unaffected. The stub now ships from `database/migrations/` inside the package, but `vendor:publish` writes it to the same place in your application.

## Upgrading from v7.0.3 to v8.0.0

### Check requirements

This release raises the minimum requirements. Make sure your application meets all of them before upgrading:

- **PHP `^8.5`** (was `^7.4 || ^8.0`)
- **Laravel 13** only — the `illuminate/*` dependencies are now constrained to `^13.0`. Support for Laravel 6 through 12 has been dropped.
- **Carbon `^3.0`** only. Support for Carbon 2 has been dropped.

### `visitor` column type

The `create_views_table` migration stub now defines the `visitor` column as a `string` (`VARCHAR(255)`) instead of `text`, so it can be indexed directly (for example to speed up `->unique()` counts).

This only affects **newly published** migrations. If you have already run the migration, no change is required — everything keeps working on the existing `text` column. If you want to adopt the new type on an existing table, add a migration:

```php
Schema::table('views', function (Blueprint $table) {
    $table->string('visitor')->nullable()->change();
});
```

Note that on large tables this rewrites the table, and any `visitor` value longer than 255 characters would be truncated (the built-in visitor identifier is 80 characters).

### Config defaults for connection and cache store

The default config now uses `null` for `models.view.connection` and `cache.store` instead of reading `env('DB_CONNECTION')` and `env('CACHE_DRIVER')`. A `null` value defers to the application's default database connection and default cache store.

This only affects **newly published** config. If you have already published `config/eloquent-viewable.php`, your copy still contains the old `env()` calls and keeps working. Two things to be aware of when adopting the new defaults:

- `CACHE_DRIVER` was renamed to `CACHE_STORE` in Laravel 11. If your published config still reads `env('CACHE_DRIVER', 'file')` while your app only sets `CACHE_STORE`, view counts are cached to the `file` store regardless of your configured cache. Setting `cache.store` to `null` (or `env('CACHE_STORE')`) resolves this.
- Switching `cache.store` to `null` means view counts are cached in your application's **default** cache store. If you relied on the previous `file` fallback, set `cache.store` explicitly instead.

### Cache key format

The internal `CacheKey` class now builds cache keys as a readable prefix followed by a hash of the count's full identity (`{prefix}:{morph class}:{key}:{digest}`), instead of the previous concatenated string of slugs. This produces shorter, fixed-length keys that stay well under backend limits (such as Memcached's 250-byte cap) and cannot collide across different models.

**No action is required.** This only affects view counts cached via `remember()`. Existing entries under the old key format are simply never read again. The first `count()` after upgrading recomputes the value from the `views` table and re-caches it under the new key. Nothing is lost, since the database remains the source of truth. Old entries expire on their own; run `php artisan cache:clear` (or clear the `eloquent-viewable` store) after deploying if you would rather remove them immediately.

The cache key string is an internal implementation detail. If you constructed `CacheKey` directly (it is not part of the public API), note that it no longer exposes the `fromViewable()` factory. The constructor takes the morph class and key of the viewable, the cache-key prefix and the identity of the source, and `make()` takes a `ViewsQuery`:

```diff
-CacheKey::fromViewable($viewable)->make($period, $unique, $collection);
+new CacheKey(
+    $viewable->getMorphClass(),
+    $viewable->getKey(),
+    config('eloquent-viewable.querying.cache.key'),
+    config('eloquent-viewable.querying.source.driver').':'.app(DatabaseSource::class)->cacheIdentity(),
+)->make(new ViewsQuery($period, $collection, $unique));
```

The entry under that key no longer holds the bare count. It holds the count together with the versions `forgetCache()` and `flushCache()` replace, so read counts through `views()` rather than from the cache store.

The following getters were removed from `CyrildeWit\EloquentViewable\Support\Period`:

```diff
-$period->getStartDateTimeString();
-$period->getEndDateTimeString();
-$period->getStartDateTimestamp();
-$period->getEndDateTimestamp();
```

Derive these values from the `DateTimeInterface` getters instead:

```diff
+$period->getStartDateTime()?->format('Y-m-d H:i:s');
+$period->getEndDateTime()?->format('Y-m-d H:i:s');
+$period->getStartDateTime()?->timestamp;
+$period->getEndDateTime()?->timestamp;
```

### Changes to the `View` contract

The `scopeWithinPeriod` method now declares a `void` return type. The contract also uses `@mixin \Illuminate\Database\Eloquent\Model` (so every Eloquent method resolves on it) and now declares the `scopeCollection()` method that already existed on the `View` model. This only affects you if you implement `CyrildeWit\EloquentViewable\Contracts\View` directly — update your signature and add the `scopeCollection()` declaration:

```diff
-public function scopeWithinPeriod(Builder $query, Period $period);
+public function scopeWithinPeriod(Builder $query, Period $period): void;

+public function scopeCollection(Builder $query, ?string $collection = null): void;
```

### Changes to the `Views` contract

Parameters are now natively typed, and the contract now declares the `useVisitor()` method that already existed on the `Views` class. This only affects you if you implement `CyrildeWit\EloquentViewable\Contracts\Views` directly — update your parameter types and add the `useVisitor()` declaration:

```diff
-public function cooldown($cooldown): self;
+public function cooldown(DateTimeInterface|int|null $cooldown): self;

-public function remember($lifetime = null): self;
+public function remember(DateTimeInterface|int|null $lifetime = null): self;

+public function useVisitor(Visitor $visitor): self;
```

### Changes to `Period`

`Period` is now a `final`, immutable (`readonly`) value object. The way you create and read periods is unchanged — the factory methods (`create()`, `since()`, `upto()`, `pastDays()`, `subDays()`, …) and `getStartDateTime()`/`getEndDateTime()` all work exactly as before. `getStartDateTime()`/`getEndDateTime()` return `?CarbonInterface`.

The following were removed and have no replacement, as they only existed to support the internal cache key:

- The `Period::PAST_*` and `Period::SUB_*` constants.
- The `sub()`, `subToday()` and `subNow()` static helpers.
- The `getSubType()`, `getSubValue()` and `hasFixedDateTimes()` methods.

Because `Period` is now immutable, its setters were also removed. Build a new period instead of mutating one:

- `setStartDateTime()`, `setEndDateTime()`, `setFixedDateTimes()`, `setSubType()`, `setSubValue()`.

If you extended `Period`, note it is now `final` and can no longer be subclassed.

### Changes to the `views()` helper

The `views()` helper now type-hints its argument as `Viewable|string`. Passing anything else will throw a `TypeError` instead of failing later:

```diff
-function views($viewable): Views;
+function views(Viewable|string $viewable): Views;
```

### Final exceptions

The `InvalidPeriod` and `ViewRecordException` exceptions are now `final`. If you extended either of them, catch them or wrap your own exception around them instead of subclassing.
