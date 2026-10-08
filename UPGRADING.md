# Upgrade Guide

## Table of contents

- [Upgrading from v8.0.0 to v9.0.0](#upgrading-from-v800-to-v900)
- [Upgrading from v7.0.3 to v8.0.0](#upgrading-from-v703-to-v800)

The version upgrade guides for versions below `v7.0.3` are still accessible in the major version branches like [`7.x`](/cyrildewit/eloquent-viewable/blob/7.x/UPGRADING.md).  

## Upgrading from v8.0.0 to v9.0.0

The requirements are unchanged: PHP 8.5 and Laravel 13. On MySQL, version 9 needs MySQL 8.0 or newer, because the
rollups of a dimension rank its values with a window function. MariaDB, Postgres and SQLite have one on every version
Laravel supports. Most applications only need the five steps below. The sections after them cover behaviour changes to
be aware of and what to change if you extended the package.

### What's new in 9.0

- **Record from a route** with the `views` middleware, without touching the controller.
- **Dashboards out of the box:** counts per hour, day, week, month or year with `countByInterval()`, a comparison with
  the previous period with `compare()`, and periods read from a URL with `Period::parse()`.
- **Rankings** of the most viewed content across every model with `Views::top()`, and `whereViewsCount()` to filter by
  views.
- **Who is looking right now:** live visitor counts and a ranking of what is being read, kept in Redis and refreshed by
  a heartbeat from the page.
- **Who viewed what:** link views to the signed-in user and ask what they have or have not seen.
- **Where views came from:** count views by source, medium, campaign, device, country or a value of your own with
  `countBy()`, and narrow any count with `whereDimension()`.
- **Scale when you need to:** buffer views in Redis and land them in batches, count a page of models in one query, and
  forget cached counts on demand.
- **Privacy:** count unique visitors without a cookie, honour Global Privacy Control, let a signed-in user opt out of
  recording and skip prefetched pages.
- **Bots:** refuse bursts of views from one visitor, and delete the bot views already in your table with
  `views:purge-bots`.
- **Testing:** `Views::fake()` with assertions, and a factory for the `View` model.
- **Extension points:** your own recording guards, stores, count sources and cooldown stores.

See the [README](README.md) for each of them.

### 1. Update the package

```bash
composer require cyrildewit/eloquent-viewable:^9
```

### 2. Update your imports

Most classes moved into a namespace per module. Search your code for `CyrildeWit\EloquentViewable\` and update each
import:

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
| `CacheKey` | `Querying\Cache\CacheKey` |
| `PendingView` | `Data\ViewRecord` |
| `Events\ViewRecorded` | `Recording\Events\ViewRecorded` |
| `ViewableObserver` | `Recording\Observers\ViewableObserver` |
| `Actions\CreateView` | `Recording\Actions\RecordView` |
| `Contracts\CreateView` | `Recording\Contracts\RecordsViews` |
| `Jobs\StoreView` | `Recording\Jobs\RecordViewJob` |
| `Exceptions\ViewRecordException` | `Recording\Exceptions\RecordingFailed` |

`Views`, `Contracts\Viewable`, `Support\Period` and `Exceptions\InvalidPeriod` did not move. `Contracts\View` and
`Contracts\Views` are gone, see [Custom `View` model](#custom-view-model) and [Custom `Views` class](#custom-views-class).

In your tests, `Bus::assertDispatched(StoreView::class)` becomes `Bus::assertDispatched(RecordViewJob::class)`.

### 3. Update your published config

The config is now grouped by module. The easiest route is to publish it again and copy your values over:

```bash
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config" --force
```

To move the keys by hand instead:

| v8 | v9 |
| --- | --- |
| `cache` | `querying.cache` |
| `queue` | `recording.queue` |
| `ignored_ip_addresses` | `recording.ignored_ip_addresses` |
| `visitor_cookie_key` | `visitor.cookie.name` |
| `ignore_bots => false` | remove `IgnoreCrawlers` from `recording.guards` |
| `honor_dnt => true` | add `IgnoreDoNotTrack` to `recording.guards` |
| `cooldown` | add `store` and `cache.store`, see below |
| `models` | unchanged |

A key left in its v8 place is ignored and its default applies, so check `ignored_ip_addresses` and `queue` in
particular. The `cooldown` block needs the new keys, otherwise every `views()` call throws `InvalidConfiguration`:

```php
'cooldown' => [
    'store' => 'session',
    'key' => 'cyrildewit.eloquent-viewable.cooldowns',
    'cache' => [
        'store' => null,
    ],
],
```

If you keep your old `recording.guards` list, add `IgnoreOptedOutViewers` to it before implementing
`Contracts\ViewerCanOptOut` on a viewer model, or the opt-out is not honoured. `php artisan views:doctor` warns about it.

Config values are now validated when they are read, so a connection, table, queue or cache store given as anything
other than a string or `null` throws `InvalidConfiguration` naming the key.

### 4. Add the new columns and indexes

The `views` table needs three new nullable columns, `viewer_type`, `viewer_id` and `context`, because every view
writes them. A composite index on `viewable_type`, `viewable_id` and `viewed_at` lets period counts read only the rows
inside the period, and an index on `viewed_at` serves retention and rollups, which scan by date across every viewable.
Your published migration has already run, so add them in a migration of your own:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('eloquent-viewable.models.view.table_name');

        Schema::connection(config('eloquent-viewable.models.view.connection'))
            ->table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->nullableMorphs('viewer'); // nullableUuidMorphs or nullableUlidMorphs to match your user model
                $blueprint->json('context')->nullable();
                $blueprint->index(['viewable_type', 'viewable_id', 'viewed_at'], "{$table}_viewable_viewed_at_index");
                $blueprint->index('viewed_at', "{$table}_viewed_at_index");
            });
    }

    public function down(): void
    {
        $table = config('eloquent-viewable.models.view.table_name');

        Schema::connection(config('eloquent-viewable.models.view.connection'))
            ->table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("{$table}_viewable_viewed_at_index");
                $blueprint->dropIndex("{$table}_viewed_at_index");
                $blueprint->dropMorphs('viewer');
                $blueprint->dropColumn('context');
            });
    }
};
```

On a large table, building an index locks writes while it runs. MySQL 8 and MariaDB 10.5 build it online. On
Postgres, use `CREATE INDEX CONCURRENTLY` with `public $withinTransaction = false;` on the migration.

Nothing is recorded in the new columns until you opt in to [recording the viewer](README.md#who-viewed-what) or pass a
[context](README.md#storing-context-with-a-view).

[Dimensions](README.md#dimensions) add no column until you list one. After listing one under
`dimensions.definitions`, run `php artisan views:dimensions`, which writes the migration that adds its column, and
migrate. If you publish the config by hand, add `dimensions` to `retention.anonymise.columns`, so anonymising clears
the dimensions marked personal.

### 5. Replace `$removeViewsOnDelete` with a method

```php
// Before
protected $removeViewsOnDelete = false;

// After
public function shouldRemoveViewsOnDelete(): bool
{
    return false;
}
```

The property is no longer read. A model that implements `Viewable` without the `InteractsWithViews` trait has to add
the method.

### Behaviour changes to check

#### Periods are half-open

A `Period` now includes its start and excludes its end, so `Period::create('2018-01-01', '2018-02-01')` is January and
`Period::upto($date)` means before `$date`. A count whose period ends exactly on a stored `viewed_at` drops by one.
Bounds built in another timezone are converted to `app.timezone` before they are compared, and `getStartDateTime()`
and `getEndDateTime()` return the converted values.

#### Prefetches, `HEAD` requests and requests without a user agent are not counted

Three new guards are on by default. `IgnorePrefetch` drops pages the browser only fetched in advance, so sites that use
prefetching or speculation rules see lower counts. `IgnoreHeadRequests` drops `HEAD` requests, such as uptime monitors.
`IgnoreMissingUserAgent` drops requests without a user agent, such as scripts and health checks; if you record views
from a client that sends none, remove it from `recording.guards`.

#### Bursts of views are not counted

`IgnoreBursts` is on by default. A visitor that opens more than 8 different models within 2 seconds is refused, and so
is every view of theirs for the next 2 minutes. Scrapers that pass for a browser stop counting, so sites with a lot of
bot traffic see lower counts. The count is also kept per network and user agent, which people behind one office or
carrier address share. If your readers come from such a network, set `recording.bursts.by` to `['visitor']`; to turn
the guard off, remove it from `recording.guards`. If you record views in bulk from your own code, such as an import,
remove the guard for that run or use `View::factory()`. Run `views:purge-bots --dry-run` to see how many bot views your
table already holds.

#### Every facade call starts a fresh builder

The `Views` facade used to reuse one builder for the whole request, so options leaked from one call into the next.
Keep a chain on one line, or hold the builder in a variable:

```php
// Before: counted $post through the leaked viewable. Now: throws InvalidViewable.
Views::forViewable($post)->record();
Views::count();

// After
$views = Views::forViewable($post);
$views->record();
$views->count();
```

`Views::shouldReceive()` and `Views::swap()` work as before.

#### `View` model events no longer fire when a view is recorded

Views are written through the query builder, so `creating` and `created` observers on the `View` model stop running.
Listen for `Recording\Events\ViewRecorded` instead, see [below](#viewrecorded-listeners).

#### Caches and cooldowns reset once

Counts cached with `remember()` are recalculated once after upgrading, because the cache key changed. Cooldowns
running at deploy time end early, once, because the session format changed.

### If you extended the package

#### `ViewRecorded` listeners

The event carries a `Data\ViewRecord` as `$record` instead of the model as `$view`, and no longer uses
`SerializesModels`:

```php
// Before
$event->view->viewable_id;

// After
$event->record->viewableId;
$event->record->viewableType::find($event->record->viewableId);
```

With the [Redis buffer](README.md#buffering-views-in-redis) the row does not exist yet when the event fires, so read
what you need from the record.

#### Custom recording action

Bind it to `Recording\Contracts\RecordsViews`. `handle()` receives a `ViewRecord` and returns `void`:

```php
// Before
public function handle(PendingView $pending): View

// After
public function handle(ViewRecord $record): void
```

To only change where views are written, [register a store](README.md#choosing-where-views-are-stored) instead.

#### Custom `View` model

`Contracts\View` is gone. Extend `Models\View`, name the class in the config and remove the container binding:

```php
'models' => [
    'view' => [
        'class' => \App\Models\View::class,
    ],
],
```

A `$table` or `$connection` property on your model now wins over `table_name` and `connection` in the config.

#### Custom `Views` class

`Contracts\Views` is gone and `Views` can no longer be replaced through the container. Type-hint `Views` instead, and
move your changes into a macro, a [recording guard](README.md#adding-a-recording-guard), a
[store](README.md#choosing-where-views-are-stored) or a [source](README.md#customizing-how-views-are-counted). A
subclass that overrides the constructor needs the new arguments: the visitor, `Recording\Recorder`, `Querying\Reader`,
`Recording\Actions\DestroyViews` and `Querying\Cache\CacheVersions`.

#### `Viewable` without `InteractsWithViews`

`scopeOrderByViews()` and `scopeOrderByUniqueViews()` on `Contracts\Viewable` take a last argument,
`array $dimensions = []`. A model that implements `Viewable` without the trait adds it to both signatures.

#### Custom `Visitor`

The contract changed:

- `isCrawler()` is gone. Add `userAgent(): ?string` and return `null` when there is none.
- Add `viewer(): ?Model`, the signed-in model or `null`.
- Add `hasGlobalPrivacyControl(): bool` and `isPrefetch(): bool`. Return `false` when the request has no such header.
- Add `isHeadRequest(): bool`, which is `true` for a `HEAD` request.

A subclass of the shipped `Visitor` that overrides its constructor drops the `CrawlerDetector` argument and passes
`Request`, `Support\Config`, `Illuminate\Contracts\Cookie\QueueingFactory` and `Illuminate\Contracts\Auth\Factory`, in
that order.

The `Visitor::DNT` constant is now `Visitor::DoNotTrackHeader`.

#### Custom `CrawlerDetector`

The detector now receives the user agent instead of reading the request:

```php
// Before
public function isCrawler(): bool

// After
public function isCrawler(?string $userAgent): bool
```

#### `CacheKey` and `RecordViewJob`

`CacheKey::make(?Period $period, bool $unique, ?string $collection)` is now
`CacheKey::make(ViewsQuery $query, ?Granularity $granularity = null, ?string $grouping = null, ?int $limit = null)`.
The constructor takes the morph class and key of the viewable, the cache-key prefix and the identity of the source.

`RecordViewJob::dispatch()` is gone. Use `dispatch(new RecordViewJob($record))`.

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
