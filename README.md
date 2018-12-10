# Eloquent Viewable

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![Travis branch](https://img.shields.io/travis/cyrildewit/eloquent-viewable/2.0.svg?style=flat-square)](https://travis-ci.org/cyrildewit/eloquent-viewable)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://styleci.io/repos/94131608)
[![Codecov branch](https://img.shields.io/codecov/c/github/cyrildewit/eloquent-viewable/2.0.svg?style=flat-square)](https://codecov.io/gh/cyrildewit/eloquent-viewable)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![license](https://img.shields.io/github/license/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://github.com/cyrildewit/eloquent-viewable/blob/master/LICENSE.md)

> **Note:** This is an unstable branch!

This Laravel >= 5.5 package allows you to associate views with Eloquent models.

Once installed you can do stuff like this:

```php
// Return total views count
$post->views()->count();

// Return total views count that have been made since 20 February 2017
$post->views()->period(Period::since('2017-02-20'))->count();

// Return total views count that have been made between 2014 and 216
$post->views()->period(Period::create('2014', '2016'))->count();

// Return total unique views count (based on visitor cookie)
$post->views()->unique()->count();

// Record a new view
$post->views()->record();

// Record a new view with session delay between views
$post->views()->sessionDelay(now()->addHours(2))->record();

// Alternatively, you can use the helper function
views($post)->doSomething();
```

## Overview

Eloquent Viewable allows you to easiliy associate views with Eloquent models. It's designed with simplicity in mind. This package will save all view records in a database table, so we can make different views counts. For example, if we want to know how many peopele has viewed a specific post between January 10 and February 17 in 2018, we can do the following: `$post->views()->period(Period::create('10-01-2018', '17-02-2018'))->count();`.

This package is not built with the intent to collect analytical data. It is made to simply store the views of a Laravel Eloquent model. You would use this package for models like: Post, Video, Profile and Hotel, but of course, you can use this package as you want.

### Features

Here are some of the main features:

* Associate views with Eloquent models
* Get total views count
* Get views count of a specific period
* Get unique views count
* Get views count of a viewable type
* Record views with session delays
* Smart views count cacher
* Ignore views from crawlers, ignored IP addresses or requests with DNT header

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package.

### Table of contents

1. [Getting Started](#getting-started)
    * [Requirements](#requirements)
    * [Installation](#installation)
2. [Usage](#usage)
    * [Preparing your model](#preparing-your-model)
    * [Recording views](#storing-views)
    * [Recording views with session delays](#recording-views-with-session-delays)
    * [Retrieving views counts](#retrieving-views-counts)
    * [Order models by views count](#order-models-by-views-count)
3. [Advanced Usage](#advanced-usage)
    * [Queuing views](#queueing-views)
    * [Caching view counts](#caching-view-counts)
4. [Extending](#extending)
    * [Using your own model](#using-your-own-model)
    * [Using a custom crawler detector](#using-a-custom-crawler-detector)

## Getting Started

### Requirements

This package requires **PHP 7+** and **Laravel 5.5+**.

Lumen is not supported!

#### Version information

| Version | Illuminate | Status         | PHP Version |
|---------|------------|----------------|-------------|
| 3.0     | 5.5 - 5.7  | _In Development_ | >= 7.1.0    |
| 2.0     | 5.5 - 5.7  | Active support | >= 7.0.0    |
| 1.0     | 5.5 - 5.6  | Bug fixes only | >= 7.0.0    |

### Installation

First, you need to install the package via Composer:

```winbatch
composer require cyrildewit/eloquent-viewable
```

Secondly, if you want to make some basic changes like giving the `views` table a different name or creating the table on a different connection, you can configure that by publishing the config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
```

Alternatively, if you want to make bigger changes to the migrations, you can publish them using:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

Finally, you need to run the `migrate` command:

```winbatch
php artisan migrate
```

#### Register service provider manually

If you prefer to register packages manually, you can add the following provider to your application's providers list.

```php
// config/app.php

'providers' => [
    // ...
    CyrildeWit\EloquentViewable\EloquentViewableServiceProvider::class,
];
```

## Usage

### Preparing your model

To associate views with a model, the model must implement the following interface and trait.

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Viewable;
use CyrildeWit\EloquentViewable\Contracts\Viewable as ViewableContract;

class Post extends Model implements ViewableContract
{
    use Viewable;

    // ...
}
```

The `Viewable` trait also adds a shortcut for `views($model)`:

```php
$post->views()->doSomething();
```

### Recording views

To make a view record, you can call the `record` method on the fluent `Views` instance.

```php
views($post)->record();
```

The best place where you should record a visitors's view would be inside your controller. For example:

```php
// PostController.php
public function show(Post $post)
{
    views($post)->record();

    return view('post.show', compact('post'));
}
// ...
```

**Note:** This package filters out crawlers by default. Be aware of this when testing, because Postman is for example also a crawler.

### Recording views with session delays

You may use the `sessionDelay` method on the `Views` instance to add a delay between view records. When you set a delay, you need to specify the number of minutes.

```php
views($post)
    ->sessionDelay($minutes)
    ->record();
```

Instead of passing the number of minutes as an integer, you can also pass a `DateTime` instance.

```php
$expiresAt = now()->addHours(3);

views($post)
    ->sessionDelay($expiresAt)
    ->record();
```

#### How it works

When recording a view with a session delay, this package will also save a snapshot of the view in the visitor's session with an expiration datetime. Whenever the visitor views the item again, this package will checks his session and decide if the view should be saved in the database or not.

### Retrieving views counts

#### Get total views count

```php
views($post)->count();
```

#### Get views count of a specific period

```php
use CyrildeWit\EloquentViewable\Support\Period;

// Example: get views count since 2017 upto 2018
views($post)
    ->period(Period::create('2017', '2018'))
    ->count();
```

The `Period` class that comes with this package provides many handy features. The API of the `Period` class looks as follows:

##### Between two datetimes

```php
$startDateTime = Carbon::createFromDate(2017, 4, 12);
$endDateTime = '2017-06-12';

Period::create($startDateTime, $endDateTime);
```

##### Since a datetime

```php
Period::since(Carbon::create(2017));
```

##### Upto a datetime

```php
Period::upto(Carbon::createFromDate(2018, 6, 1));
```

##### Since past

Uses `Carbon::today()` as start datetime minus the given unit.

```php
Period::pastDays(int $days);
Period::pastWeeks(int $weeks);
Period::pastMonths(int $months);
Period::pastYears(int $years);
```

##### Since sub

Uses `Carbon::now()` as start datetime minus the given unit.

```php
Period::subSeconds(int $seconds);
Period::subMinutes(int $minutes);
Period::subHours(int $hours);
Period::subDays(int $days);
Period::subWeeks(int $weeks);
Period::subMonths(int $months);
Period::subYears(int $years);
```

#### Get total unique views count

If you only want to retrieve the unique views count, can you simply add the `unique` method to the chain.

```php
views($post)
    ->unique()
    ->count();
```

### Order models by views count

The `Viewable` trait adds two scopes to your model: `scopeOrderByViews` and `scopeOrderByUniqueViews`.

#### Retrieve viewable models by views count

```php
Post::orderByViews()->get(); // descending
Post::orderByViews('asc')->get(); // ascending
```

#### Retrieve viewable models by unique views count

```php
Post::orderByUniqueViews()->get(); // descending
Post::orderByUniqueViews('asc')->get(); // ascending
```

#### Retrieve viewable models by views count and period

```php
Post::orderByViews()->get();
```

### Get views count of viewable type

If you want to know how many views a specific viewable type has, you can use the `getViewsCountByType` method on the `Views` class.

```php
views()->countByType(Post::class);
views()->countByType('App\Post');
```

You can also pass an instance of an Eloquent model. It will get the fully qualified class name by calling the `getMorphClass` method on the model.

```php
views()->countByType($post);
```

### Advanced Usage

#### Supplying your own visitor's IP Address

If you are using this package via a RESTful API, you might want to supply your own visitor's IP Address, otherwise this package will use the IP Address of the requester.

```php
views($post)
    ->overrideIpAddress('Your IP Address')
    ->record();
```

#### Queuing views

#### Caching view counts

#### Extending

If you want to extend or replace one of the core classes with your own implementations, you can override them:

* `CyrildeWit\EloquentViewable\View`
* `CyrildeWit\EloquentViewable\Resolvers\IpAddressResolver`
* `CyrildeWit\EloquentViewable\CrawlerDetector\CrawlerDetectAdapter`

_**Note:** Don't forget that all custom classes must implement their original interfaces_

#### Replace `View` model with custom implementation

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\View::class,
    \App\CustomView::class
);
```

#### Replace `IpAddressResolver` class with custom implementation

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Contracts\IpAddressResolver::class,
    \App\Resolvers\IpAddressResolver::class
);
```

#### Replace `CrawlerDetectAdapter` class with custom implementation

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector::class,
    \App\Services\CrawlerDetector\CustomAdapter::class
);
```

## Upgrading

Please see [UPGRADING](UPGRADING.md) for detailed upgrade guide.

## Changelog

Please see [CHANGELOG](CHANGELOG-2.0.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

* **Cyril de Wit** - _Initial work_ - [cyrildewit](https://github.com/cyrildewit)

See also the list of [contributors](https://github.com/cyrildewit/perceptor/graphs/contributors) who participated in this project.

**Helpful Resources:**

* [Implementing A Page View Counter In Laravel](https://stidges.com/implementing-a-page-view-counter-in-laravel) - **[Stidges](https://github.com/stidges)**

## Alternatives

* [antonioribeiro/tracker](https://github.com/antonioribeiro/tracker)
* [foothing/laravel-simple-pageviews](foothing/laravel-simple-pageviews)
* [awssat/laravel-visits](https://github.com/awssat/laravel-visits)
* [Kryptonit3/Counter](https://github.com/Kryptonit3/Counter)
* [fraank/ViewCounter](https://github.com/fraank/ViewCounter)

Feel free to add more alternatives!

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
