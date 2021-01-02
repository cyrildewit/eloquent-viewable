# Upgrade Guide

## Table of contents

- [Upgrading from v6.0.1 to v6.0.2](#upgrading-from-v601-to-v602)
- [Upgrading from v5.2.1 to v6.0.0](#upgrading-from-v521-to-v600)
- [Upgrading from v5.2.0 to v5.2.1](#upgrading-from-v520-to-v521)
- [Upgrading from v5.1.0 to v5.2.0](#upgrading-from-v510-to-v520)
- [Upgrading from v5.0.0 to v5.1.0](#upgrading-from-v500-to-v510)
- [Upgrading from v4.1.1 to v5.0.0](#upgrading-from-v411-to-v500)
- [Upgrading from v2.4.3 to v3.0.0](#upgrading-from-v243-to-v300)
- [Upgrading from v2.4.2 to v2.4.3](#upgrading-from-v242-to-v243)
- [Upgrading from v2.1.0 to v2.2.0](#upgrading-from-v210-to-v220)
- [Upgrading from v2.0.0 to v2.1.0](#upgrading-from-v200-to-v210)
- [Upgrading from v1.0.5 to v2.0.0](#upgrading-from-v105-to-v200)

## Upgrading from v6.0.1 to v6.0.2

There are no manual changes needed.

## Upgrading from v6.0.0 to v6.0.1

There are no manual changes needed.

## Upgrading from v5.2.1 to v6.0.0

### Check requirements

Make sure you're on a supported PHP version. These are ^7.4 or ^8.0.

### Update config file

If you have published the config file of this package, you will have to remove the `cache.lifetime_in_minutes` key manually.

```diff
-/*
- * Default lifetime of cached views count in minutes.
- */
-'lifetime_in_minutes' => 60,
```

### Changes to `orderByViews` query scope

The parameters of the `orderByViews` query scope in the `Viewable` contract have been changed.

```diff
-public function scopeOrderByViews(Builder $query, string $direction = 'desc', $period = null): Builder;
+public function scopeOrderByViews(Builder $query, string $direction = 'desc', ?Period $period = null, ?string $collection = null, bool $unique = false, string $as = 'views_count'): Builder;
```

### Changes to `orderByUniqueViews` query scope

The parameters of the `orderByViews` query scope in the `Viewable` contract have been changed.

```diff
-public function scopeOrderByUniqueViews(Builder $query, string $direction = 'desc', $period = null): Builder;
+public function scopeOrderByUniqueViews(Builder $query, string $direction = 'desc', ?Period $period = null, ?string $collection = null, string $as = 'unique_views_count'): Builder;
```

### The default cache lifetime has been changed

The default cache lifetime functionality has been removed. The default value is now `null`, which means it will be cached forever.

Look for all occurrences of the `remember` method and pass the desired lifetime.

### Changes to the `Views` contract

* The `$viewable` argument of the `forViewable` method cannot be `null` anymore. 
* The `record` method has now `bool` as return typehint.
* The `destroy` method has now `void` as return typehint.
* The `$period` argument of the `period` method has now a typehint of `?Period`. 
* The `$name` argument of the `period` method has now a typehint of `?string`. 
* The default value `null` of the `$lifetime` argument of the `remember` method has been removed. 

### Changes to the `Visitor` contract

* The `ip` method has now `?string` as return typehint.

### The behavior of `count` method has been changed

When the collection is not given, thus null, all views will be counted.

Let's say we have three views of which one has been stored in a collection called `abc`.

**Before:** `->count()` will return `2`
**After:** `->count()` will return `3`

### Exception when recording a view for a viewable type

The `record` method will now throw an `ViewRecordException` exception when trying to record a view for a viewable type.


### Internal classes have changed

The following internal classes have changed. If you're extending or using them, check for any breaking changes.

* `CyrildeWit\EloquentViewable\CacheKey`
* `CyrildeWit\EloquentViewable\CooldownManager`
* `CyrildeWit\EloquentViewable\Views`
* `CyrildeWit\EloquentViewable\Visitor`

## Upgrading from v5.2.0 to v5.2.1

There are no manual changes needed.

## Upgrading from v5.1.0 to v5.2.0

There are no manual changes needed.

## Upgrading from v5.0.0 to v5.1.0

### Check usages of views helper for null viewable

The following code is not valid anymore:

```php
views()->count();
```

Use the `View` Eloquent model.

## Upgrading from v4.1.1 to v5.0.0

First, you need to read the changelog and take a look at the comparison between your current used version and `v5.0.0`, so you have a broad overview of what has changed.

This package now requires Laravel `^6.0` or `^7.0`.

### Changes to underlying queries

The underlying queries that this package creates has been changed completely. For example, the order by query scopes no no longer uses a left join.

In the most basic use cases of this package, you will likely experience no issues at all, but it's still possible. Please check all your usages of the `Views` class (`views()`) manually for broken functionality.

### Update Eloquent model definitions

The `CyrildeWit\EloquentViewable\Viewable` trait has been renamed to `CyrildeWit\EloquentViewable\InteractsWithViews`.

### Update config file

If you have published the config file of this package, you will have to rename the key of `session` to `cooldown`.

The current cooldown configuration is posted below:

```php
/*
|--------------------------------------------------------------------------
| Cooldown Configuration
|--------------------------------------------------------------------------
*/
'cooldown' => [

    /*
     * Everthing will be stored under the following key in the session.
     */
    'key' => 'cyrildewit.eloquent-viewable.cooldowns',

],
```

### Update `views` database table

The type of the primary key `id` has been changed to a big integer.

Create a new migration for your views table with the following contents:

```php
$table->bigIncrements('id')->change();
```

### Update session delays

The `delayInSession` method of the `Views` builder has been renamed to `cooldown`.

Example:

```php
views($post)->cooldown(now()->addMinutes(30))->record();
```

### Update `overrideIpAddress` and `overrideVisitor` usages

You can no longer override the ip address and visitor id using the `overrideIpAddress` and `overrideVisitor` method on the `Views` builder.

You will have to create your own `Visitor` class to provide custom values.

Take a look a the [documentation](README.md#custom-information-about-visitor) on how to do this.

### Remove custom HeaderResolver and `IpAddressResolver`

If you have created your own `HeaderResolver` or `IpAddressResolver`, refactor your code to adhere to the new `Visitor` class implementation.

### Update usages of `VisitorCookieRepository`

The logic from the `VisitorCookieRepository` repository has been moved to the new `Visitor` class. If you have used or overwritten this class, you will have to update your code.

### Remove usages of `uniqueVisitor` scope on `View` model

Although, it's never documented you may have used the internal `uniqueVisitor` scope of the `View` eloquent model. This scope has been removed. If you rely on it, you will need to extend the `View` class and add this method manually.

### Update usages of `ViewSessionHistory`

If you have used the internal `ViewSessionHistory` you will have to update your code to use the new `CooldownManager`.

### Remove usages of `OrderByViewsScope` class

This class was used internally


## Upgrading from v2.4.3 to v3.0.0

First, you need to read the changelog, so you have a broad overview of what has changed.

**In Progress**

## Upgrading from v2.4.2 to v2.4.3

Run the following migration to update the `visitor` column.

```php
$table->text('visitor')->change();
```

## Upgrading from v2.1.0 to v2.2.0

### Update config file

If you have published the config file of this package, you will have to copy the following snippet to your config file:

```php
/*
|--------------------------------------------------------------------------
| Session Configuration
|--------------------------------------------------------------------------
*/
'session' => [

    /*
     * Everthing will be stored under the following key.
     */
    'key' => 'cyrildewit.eloquent-viewable.session',

],
```

Take a look at the [original file](https://github.com/cyrildewit/eloquent-viewable/blob/2.1/publishable/config/eloquent-viewable.php) to find the right location.

## Upgrading from v2.0.0 to v2.1.0

There are no manual changes needed.

## Upgrading from v1.0.5 to v2.0.0

The package has been renamed from `Laravel Page View Counter` to `Eloquent Viewable`.

### PHP

While `v1.0.5` already required PHP 7.0 and higher, it is now added to the composer.json file.

### License

The license has been changed from `MIT` to `Apache 2.0`.

### Require the new composer package

You can install the new package via composer using:

```winbatch
composer require cyrildewit/eloquent-viewable
```

### Update your `config/app.php`

Replace `CyrildeWit\PageViewCounter\PageViewCounterServiceProvider::class` with `CyrildeWit\EloquentViewable\EloquentViewableServiceProvider::class` in providers.

### Update database tables

If your app is in development, you can publish the new migration file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

Otherwise, you can use the `update_views_table ` migration to upgrade. It can be found at [resources/database/](resources/database/migrations/2018_06_07_311156_update_views_table.php).

### Update the config file

First you have to publish the new config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
```

Read this config file and update the fields if needed!

### Prepare your viewable models again

- Replace `use CyrildeWit\PageViewCounter\Traits\HasPageViewCounter;` with `use CyrildeWit\EloquentViewable\Viewable;`.
- Replace `use HasPageViewCounter;` with `use Viewable;`.

For example:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Viewable;

class Post extends Model
{
    use Viewable;

    // ...
}
```

### Viewable model methods

#### Change `->addPageView()` to `->addView()`

- Find all usages of `addPageView()` and replace it with `addView()`.

#### Change `->addPageViewThatExpiresAt()` to `->addView()`

**Note:** this feature has been made available again in `v2.1.0`! See the [README](README.md)!

- Find all usages of `addPageViewThatExpiresAt(<DateTime>)` and replace it with `addView()`.

#### Change all `->getPageViews<suffix>()` to `->getViews()`

- Find all usages of `getPageViews()` and replace it with `getViews()`.
- Find all usages of `getPageViewsFrom(<DateTime>)`and replace it with `getViews(Period::since(<DateTime>))`.
- Find all usages of `getPageViewsBefore(<DateTime>)`and replace it with `getViews(Period::upto(<DateTime>))`.
- Find all usages of `getPageViewsBetween(<DateTime>)`and replace it with `getViews(Period::create(<DateTime>, <DateTime>))`.
- Find all usages of `getUniquePageViews()` and replace it with `getUniqueViews()`.
- Find all usages of `getUniquePageViewsFrom(<DateTime>)`and replace it with `getUniqueViews(Period::since(<DateTime>))`.
- Find all usages of `getUniquePageViewsBefore(<DateTime>)`and replace it with `getUniqueViews(Period::upto(<DateTime>))`.
- Find all usages of `getUniquePageViewsBetween(<DateTime>)`and replace it with `getUniqueViews(Period::create(<DateTime>, <DateTime>))`.

### DateTransformer

The DateTransformers feature has been removed from v2.0.0.
