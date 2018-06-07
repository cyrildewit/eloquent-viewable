# Upgrade Guide

- [Upgrading from 2.0.0 to 2.1.0](#upgrading-from-200-to-210)
- [Upgrading from 1.0.5 to 2.0.0](#upgrading-from-105-to-200)

## Upgrading from 2.0.0 to 2.1.0

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

## Upgrading from 1.0.5 to 2.0.0

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

### Update the migrations

If your app is in development, you can publish the new migration file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

Otherwise you have to create the following migration for yourself:

```php
Schema::table('page-visits', function (Blueprint $table) {
    $table->string('ip_address')->nullable();
});
```

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
