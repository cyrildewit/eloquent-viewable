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

### Removed `Period` getters

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

The `Period` class now works exclusively with Carbon instances internally, and a couple of getters can now return `null`.

- `Period::sub()` narrowed its first parameter from `DateTimeInterface` to `CarbonInterface`. Passing a plain `\DateTime` or `\DateTimeImmutable` will no longer work — wrap it with `Carbon::instance(...)` first.
- `getStartDateTime()` and `getEndDateTime()` now return `?CarbonInterface` (was `?DateTimeInterface`).
- `getSubType()` now returns `?string` (was `string`).
- `getSubValue()` now returns `?int` (was `int`).

### Changes to the `views()` helper

The `views()` helper now type-hints its argument as `Viewable|string`. Passing anything else will throw a `TypeError` instead of failing later:

```diff
-function views($viewable): Views;
+function views(Viewable|string $viewable): Views;
```

### Final exceptions

The `InvalidPeriod` and `ViewRecordException` exceptions are now `final`. If you extended either of them, catch them or wrap your own exception around them instead of subclassing.
