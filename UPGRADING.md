# Upgrade Guide

## Table of contents

- [Upgrading from v7.0.3 to v8.0.0](#upgrading-from-v703-to-v800)

The version upgrade guides for versions below `v7.0.3` are still accessible in the major version branches like [`7.x`](/cyrildewit/eloquent-viewable/blob/7.x/UPGRADING.md).  

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

The cache key string is an internal implementation detail. If you constructed `CacheKey` directly (it is not part of the public API), note that it now requires the cache-key prefix as a second constructor argument and no longer exposes the `fromViewable()` factory:

```diff
-CacheKey::fromViewable($viewable)->make($period, $unique, $collection);
+(new CacheKey($viewable, config('eloquent-viewable.cache.key')))->make($period, $unique, $collection);
```

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
