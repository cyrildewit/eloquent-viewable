# Eloquent Viewable

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![Travis branch](https://img.shields.io/travis/cyrildewit/eloquent-viewable/2.0.svg?style=flat-square)](https://travis-ci.org/cyrildewit/eloquent-viewable)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://styleci.io/repos/94131608)
[![Codecov branch](https://img.shields.io/codecov/c/github/cyrildewit/eloquent-viewable/2.0.svg?style=flat-square)](https://codecov.io/gh/cyrildewit/eloquent-viewable)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![Built For Laravel](https://img.shields.io/badge/built%20for-laravel-blue.svg?style=flat-square)](http://laravel.com)
[![license](https://img.shields.io/github/license/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://github.com/cyrildewit/eloquent-viewable/blob/master/LICENSE.md)

This Laravel >= 5.5 package allows you to add a view counter to your Eloquent models.

Once installed you can do stuff like this:

```php
// Get the total number of views
$post->getViews();

// Get the total number of views since a specific date
$post->getViewsSince(Carbon::parse('2007-05-21 12:23:00'));

// Get the total number of views upto a specific date
$post->getViewsUpto(Carbon::parse('2013-05-21 00:00:00'));

// Get the total number of views between the given date range
$post->getViewsBetween(Carbon::parse('2014-00-00 00:00:00'), Carbon::parse('2016-00-00 00:00:00'));

// Store a new view in the database
$post->addView();
```

## Overview

Eloquent Viewable is a powerful, flexible and easy to use Laravel package for adding a page view counter to your Eloquent models. It's designed to be flexible and useful for various projects.

This package is not built with the intent to collect analytical data. It is made to simply store the views of a Laravel Eloquent model. You would use our trait for models like: Post, Video, Course and Hotel, but of course, you can use this package as you want.

### Features

Here are some of the main features:

* Store model views
* Get the total number (unique) views
* Get the total number (unique) views since a specific date
* Get the total number (unique) views upto a specific date
* Cache the retrieved views counts
* Queue the views before saving them in the database to prevent slow requests

Feature requests are very welcome! Create an issue with [Feature Request] as prefix or send a pull request.

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package. If you have any questions about this package or if you discover any security-related issues, then feel free to get in touch with me at `github@cyrildewit.nl`.

### Table of contents

1. [Getting Started](#getting-started)
    * [Version Information](#version-information)
    * [Installation](#installation)
2. [Usage](#usage)
    * [Prepare Viewable Model](#prepare-viewable-model)
    * [Saving new views](#saving-new-vies)
    * [Retrieving views counts](#retrieving-views-counts)
    * [Scopes](#scopes)
3. [Configuration](#configuration)
    * [Queue the ProcessView job](#queue-the-processview-job)
    * [Extending](#extending)
4. [Under the hood](#under-the-hood)
    * [List of properties/methods that the trait adds to your model](#list-of-propertiesmethods-that-the-trait-adds-to-your-model)

## Getting Started

### Version Information

| Version | Illuminate    | Status         | PHP Version |
|---------|---------------|----------------|-------------|
| 2.x     | 5.5.x - 5.6.x | Active support | >= 7.0.0    |
| 1.x     | 5.5.x - 5.6.x | Bug fixes only | >= 7.0.0    |

### Installation

Before you can use this package you have to install it with composer.

You can install the package via composer:

```winbatch
composer require cyrildewit/eloquent-viewable
```

Optionally, you can add the service provider in the `config/app.php` file. Otherwise this can be done via automatic package discovery.

```php
'providers' => [
    // ...
    CyrildeWit\EloquentViewable\EloquentViewableServiceProvider::class,
];
```

You can publish the migration with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

After publishing the migration file you can create the `view` table by running the migrations. However, if you already have a table named `views`, you can change this name in the config. Search for 'table_names->views' and change the value to something unique.

```winbatch
php artisan migrate
```

You can publish the config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
```

## Usage

In the following sections, you will find information about the usage of this package.

### Prepare Viewable Model

First add the `CyrildeWit\EloquentViewable\Traits\Viewable` trait to your viewable Eloquent model(s). The trait will add some core functionality to your model to get the page views count and store them. After adding the trait to your model, you can optionally implement the `ViewableContract`.

Here's an example of an Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Traits\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Traits\Viewable as ViewableContract;

class Article extends Model implements ViewableContract
{
    use Viewable;

    // ...
}
```

**Tip!** To see which properties and methods this trait adds to your model look at the bottom of this documentation or [click here](#list-of-propertiesmethods-that-the-trait-adds)!

### Saving new views

After adding the trait to your model, some methods will be available. `addView()` is one of them. It will simply store a new page view in the database. The best place where you should put it is inside your controller. If you're following the CRUD standard, it would be the `@show` method.

```php
$post->addView();
```

Let's assume where are handling the page views of a post. `$post` contains an instance of our Eloquent model `App\Models\Post`.

```php
// ...
public function show(Post $post)
{
    $post->addView();

    return view('blog.post', compact('post'));
}
// ...
```

**Note:** If you want to queue this job, you can turn this on in the configuration! See the [Queue the ProcessView job](#queue-the-processview-job) section!

### Retrieving views counts

When retrieving views counts from the database, the values will be stored in the cache for a while. You can configure this in the config file.

```php
// Retrieve the total (unique) views
$post->getViews();
$post->getUniqueViews();

// Retrieve the total (unique) views that are stored after the given date
$post->getViewsSince(Carbon::parse('2007-05-21 12:23:00'));
$post->getUniqueViewsSince(Carbon::parse('2007-05-21 12:23:00'));

// Retrieve the total (unique) views that are stored before the given date
$post->getViewsSince(Carbon::parse('2013-05-21 00:00:00'));
$post->getUniqueViewsSince(Carbon::parse('2013-05-21 00:00:00'));

// Retrieve the total (unique) views that are stored between the two given dates
$post->getViewsSince(Carbon::parse('2014-00-00 00:00:00'), Carbon::parse('2016-00-00 00:00:00'));
$post->getUniqueViewsSince(Carbon::parse('2014-00-00 00:00:00'), Carbon::parse('2016-00-00 00:00:00'));
```

You can use the following methods to retrieve the total number of views in the past seconds, minutes, days, weeks, months and years.

```php
// Normal
$post->getViewsOfPastSeconds(30);
$post->getViewsOfPastMinutes(15);
$post->getViewsOfPastDays(2);
$post->getViewsOfPastWeeks(2);
$post->getViewsOfPastMonths(4);
$post->getViewsOfPastYears(5);

// Unique
$post->getUniqueViewsOfPastSeconds(30);
$post->getUniqueViewsOfPastMinutes(15);
$post->getUniqueViewsOfPastDays(2);
$post->getUniqueViewsOfPastWeeks(2);
$post->getUniqueViewsOfPastMonths(4);
$post->getUniqueViewsOfPastYears(5);
```

### Scopes

#### Retrieve Viewable models by views count

```php
$sortedPosts = Post::orderByViewsCount()->get();
$sortedPosts = Post::orderByViewsCount('asc')->get();
```

## Configuration

### Queue the ProcessView job

When calling the `->addView()` method on your model, it will save a new view in the database with some data. Because this can slow down your application, you can turn queuing on by changing the value of `store_new_view` under `jobs` in the configuration file. Make sure that you that your app is ready for queuing. If not, see the official [Laravel documentation](https://laravel.com/docs/5.6/queues) for more information!

### Extending

If you want to extend or replace one of the core classes with your own implementations, you can override them:

* `CyrildeWit\Eloquent\Viewable\Models\View`
* `CyrildeWit\Eloquent\Viewable\Services\ViewableService`

_**Note:** Don't forget that all custom classes must implement their original interfaces_

#### Replace model class with custom implementation

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\Models\View::class,
    \App\Models\CustomView::class
);
```

#### Replace service class with custom implementation

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Contracts\Services\ViewableService::class,
    \App\Services\CustomViewableService::class
);
```

## Under the hood

### List of properties/methods that the trait adds to your model

* `public function views();`
* `public function getViews();`
* `public function getViewsSince();`
* `public function getViewsUpto();`
* `public function getViewsBetween();`
* `public function getUniqueViews();`
* `public function getUniqueViewsSince();`
* `public function getUniqueViewsUpto();`
* `public function getUniqueViewsBetween();`
* `public function getViewsOfPastSeconds($seconds);`
* `public function getViewsOfPastMinutes();`
* `public function getViewsOfPastDays();`
* `public function getViewsOfPastWeeks();`
* `public function getViewsOfPastMonths();`
* `public function getViewsOfPastYears();`
* `public function getUniqueViewsOfPastSeconds();`
* `public function getUniqueViewsOfPastMinutes();`
* `public function getUniqueViewsOfPastDays();`
* `public function getUniqueViewsOfPastWeeks();`
* `public function getUniqueViewsOfPastMonths();`
* `public function getUniqueViewsOfPastYears();`
* `public addView()`
* `public removeViews()`
* `public scopeOrderByViews()`

## Changelog

Please see [CHANGELOG](CHANGELOG-2.0.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

* [Cyril de Wit](https://github.com/cyrildewit)
* [All Contributors](../../contributors)

## License

The Apache 2.0 license. Please see [License File](LICENSE.md) for more information.
