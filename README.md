# Eloquent Viewable

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![run-tests](https://github.com/cyrildewit/eloquent-viewable/workflows/run-tests/badge.svg)](https://github.com/cyrildewit/eloquent-viewable/actions)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://styleci.io/repos/94131608)
[![Codecov branch](https://img.shields.io/codecov/c/github/cyrildewit/eloquent-viewable/master.svg?style=flat-square)](https://codecov.io/gh/cyrildewit/eloquent-viewable)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-viewable)
[![license](https://img.shields.io/github/license/cyrildewit/eloquent-viewable.svg?style=flat-square)](https://github.com/cyrildewit/eloquent-viewable/blob/master/LICENSE.md)

This Laravel >= 6.0 package allows you to associate views with Eloquent models.

Once installed you can do stuff like this:

```php
// Return total views count
views($post)->count();

// Return total views count that have been made since 20 February 2017
views($post)->period(Period::since('2017-02-20'))->count();

// Return total views count that have been made between 2014 and 216
views($post)->period(Period::create('2014', '2016'))->count();

// Return total unique views count (based on visitor cookie)
views($post)->unique()->count();

// Record a view
views($post)->record();

// Record a view with a cooldown
views($post)->cooldown(now()->addHours(2))->record();
```

## Overview

Sometimes you don't want to pull in a third-party service like Google Analytics to track your application's page views. Then this package comes in handy. Eloquent Viewable allows you to easiliy associate views with Eloquent models. It's designed with simplicity in mind.

This package stores each view record individually in the database. The advantage of this is that it allows us to make very specific counts. For example, if we want to know how many people has viewed a specific post between January 10 and February 17 in 2018, we can do the following: `$post->views()->period(Period::create('10-01-2018', '17-02-2018'))->count();`. The disadvantage of this is that your database can **grow rapidly in size** depending on the amount of visitors your application has.

### Features

Here are some of the main features:

* Associate views with Eloquent models
* Get total views count
* Get views count of a specific period
* Get unique views count
* Get views count of a viewable type (Eloquent model class)
* Order viewables by views
* Set a cooldown between views
* Elegant cache wrapper built-in
* Ignore views from crawlers, ignored IP addresses or requests with DNT header

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package.

### Table of contents

1. [Getting Started](#getting-started)
    * [Requirements](#requirements)
    * [Installation](#installation)
2. [Usage](#usage)
    * [Preparing your model](#preparing-your-model)
    * [Recording views](#recording-views)
    * [Setting a cooldown](#setting-a-cooldown)
    * [Retrieving views counts](#retrieving-views-counts)
        * [Get total views count](#get-total-views-count)
        * [Get views count of a specific period](#get-views-count-of-a-specific-period)
        * [Get total unique views count](#get-total-unique-views-count)
    * [Order models by views count](#order-models-by-views-count)
        * [Order by views count](#ordery-by-views-count)
        * [Order by unique views count](#order-by-unique-views-count)
        * [Order by views count within the specified period](#order-by-views-count-within-the-specified-period)
        * [Order by views count within the specified collection](#order-by-views-count-within-the-specified-collection)
    * [Get views count of viewable type](#get-views-count-of-viewable-type)
    * [View collections](#view-collections)
    * [Remove views on delete](#remove-views-on-delete)
    * [Caching view counts](#caching-view-counts)
3. [Optimizing](#optimizing)
    * [Benchmarks](#benchmarks)
4. [Extending](#extending)
    * [Custom Visitor information](#custom-visitor-information)
    * [Using your own View Eloquent model](#using-your-own-view-eloquent-model)
    * [Using a custom crawler detector](#using-a-custom-crawler-detector)
    * [Adding macros to the Views class](#adding-macros-to-the-views-class)

## Getting Started

### Requirements

This package requires **PHP 7.2+** and **Laravel 6+**.

Support for Lumen is not maintained!

#### Version information

| Version | Illuminate    | Status         | PHP Version |
|---------|---------------|----------------|-------------|
| ^5.0    | 7.x.x - 7.x.x | Active support | >= 7.2.0    |
| ^4.0    | 5.5.x - 5.8.x | Only bug fixes | >= 7.1.0    |
| ^3.0    | 5.5.x - 5.8.x | End of life    | >= 7.1.0    |
| ^2.0    | 5.5.x - 5.7.x | End of life    | >= 7.0.0    |
| ^1.0    | 5.5.x - 5.6.x | End of life    | >= 7.0.0    |

### Installation

First, you need to install the package via Composer:

```winbatch
composer require cyrildewit/eloquent-viewable
```

Secondly, you can publish the migrations with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

Finally, you need to run the `migrate` command:

```winbatch
php artisan migrate
```

You can optionally publish the config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
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

To associate views with a model, the model **must** implement the following interface and trait:

* **Interface:** `CyrildeWit\EloquentViewable\Contracts\Viewable`
* **Trait:** `CyrildeWit\EloquentViewable\Viewable`

Example:

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
```

**Note:** This package filters out crawlers by default. Be aware of this when testing, because Postman is for example also a crawler.

### Setting a cooldown

You may use the `cooldown` method on the `Views` instance to add a cooldown between view records. When you set a cooldown, you need to specify the number of minutes.

```php
views($post)
    ->cooldown($minutes)
    ->record();
```

Instead of passing the number of minutes as an integer, you can also pass a `DateTime` instance.

```php
$expiresAt = now()->addHours(3);

views($post)
    ->cooldown($expiresAt)
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

// Example: get views count from 2017 upto 2018
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

If you only want to retrieve the unique views count, you can simply add the `unique` method to the chain.

```php
views($post)
    ->unique()
    ->count();
```

### Order models by views count

The `Viewable` trait adds two scopes to your model: `orderByViews` and `orderByUniqueViews`.

#### Order by views count

```php
Post::orderByViews()->get(); // descending
Post::orderByViews('asc')->get(); // ascending
```

#### Order by unique views count

```php
Post::orderByUniqueViews()->get(); // descending
Post::orderByUniqueViews('asc')->get(); // ascending
```

#### Order by views count within the specified period

```php
Post::orderByViews('asc', Period::pastDays(3))->get();  // descending
Post::orderByViews('desc', Period::pastDays(3))->get(); // ascending
```

And of course, it's also possible with the unique views variant:

```php
Post::orderByUniqueViews('asc', Period::pastDays(3))->get();  // descending
Post::orderByUniqueViews('desc', Period::pastDays(3))->get(); // ascending
```

#### Order by views count within the specified collection

```php
Post::orderByViews('asc', null, 'custom-collection')->get();  // descending
Post::orderByViews('desc', null, 'custom-collection')->get(); // ascending

Post::orderByUniqueViews('asc', null, 'custom-collection')->get();  // descending
Post::orderByUniqueViews('desc', null, 'custom-collection')->get(); // ascending
```

### Get views count of viewable type

If you want to know how many views a specific viewable type has, you need to pass an empty Eloquent model to the `views()` helper like so:

```php
views(new Post())->count();
```

You can also pass a fully qualified class name. The package will then resolve an instance from the application container.

```php
views(Post::class)->count();
views('App\Post')->count();
```

### View collections

If you have different types of views for the same viewable type, you may want to store them in their own collection.

```php
views($post)
    ->collection('customCollection')
    ->record();
```

To retrieve the views count in a specific collection, you can reuse the same `collection()` method.

```php
views($post)
    ->collection('customCollection')
    ->count();
```

### Remove views on delete

To automatically delete all views of an viewable Eloquent model on delete, you can enable it by setting the `removeViewsOnDelete` property to `true` in your model definition.

```php
protected $removeViewsOnDelete = true;
```

### Caching view counts

Caching the views count can be challenging in some scenarios. The period can be for example dynamic which makes caching not possible. That's why you can make use of the in-built caching functionality.

To cache the views count, simply add the `remember()` method to the chain.

Examples:

```php
views($post)->remember()->count();
views($post)->period(Period::create('2018-01-24', '2018-05-22'))->remember()->count();
views($post)->period(Period::upto('2018-11-10'))->unique()->remember()->count();
views($post)->period(Period::pastMonths(2))->remember()->count();
views($post)->period(Period::subHours(6))->remember()->count();
```

The default lifetime is configurable through the config file. Alternatively, you can pass a custom lifetime to the `remember` method.

```php
views($post)
    ->remember(now()->addHours(6))
    ->count();
```

## Optimizing

### Benchmarks

<!-- todo -->

### Database indexes

The default `views` table migration file has already two indexes for `viewable_id` and `viewable_id`.

If you have enough storage available, you can add another index for the `visitor` column. Depending on the amount of views, this may speed up your queries in some cases.


## Extending

If you want to extend or replace one of the core classes with your own implementations, you can override them:

* `CyrildeWit\EloquentViewable\View`
* `CyrildeWit\EloquentViewable\Visitor`
* `CyrildeWit\EloquentViewable\CrawlerDetectAdapter`

_**Note:** Don't forget that all custom classes must implement their original interfaces_

### Custom Visitor information

The `Visitor` class is responsible of providing the `Views` builder information about the visitor. You can override this class globally or locally.

Create you own `Visitor` class in your Laravel application and implement the `CyrildeWit\EloquentViewable\Contracts\Visitor` interface. Create the required methods by the interface.

#### Globally

Simply bind your custom `Visitor` implementation to the `CyrildeWit\EloquentViewable\Contracts\Visitor` contract.

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\Visitor::class,
    \App\Support\Visitor::class
);
```

#### Locally

You can also set the visitor instance using the `useVisitor` setter method on the `Views` builder.

```php
use App\Support\Visitor;

views($post)
    ->useVisitor(new Visitor()) // or app(visitor::class)
    ->record();
```

### Using your own `View` Eloquent model

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\View::class,
    \App\CustomView::class
);
```

### Using a custom crawler detector

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector::class,
    \App\Services\CrawlerDetector\CustomAdapter::class
);
```

### Adding macros to the Views class

```php
use CyrildeWit\EloquentViewable\Views;

Views::macro('countAndCache', function () {
    return $this->remember()->count();
});
```

Now you're able to use this shorthand like this:

```php
views($post)->countAndCache();

Views::forViewable($post)->countAndCache();
```

## Upgrading

Please see [UPGRADING](UPGRADING.md) for detailed upgrade guide.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

* **Cyril de Wit** - _Initial work_ - [cyrildewit](https://github.com/cyrildewit)

See also the list of [contributors](https://github.com/cyrildewit/eloquent-viewable/graphs/contributors) who participated in this project.

**Helpful Resources:**

* [Implementing A Page View Counter In Laravel](https://stidges.com/implementing-a-page-view-counter-in-laravel) - **[Stidges](https://github.com/stidges)**

## Alternatives

* [antonioribeiro/tracker](https://github.com/antonioribeiro/tracker)
* [foothing/laravel-simple-pageviews](https://github.com/foothing/laravel-simple-pageviews)
* [awssat/laravel-visits](https://github.com/awssat/laravel-visits)
* [Kryptonit3/Counter](https://github.com/Kryptonit3/Counter)
* [fraank/ViewCounter](https://github.com/fraank/ViewCounter)

Feel free to add more alternatives!

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE) file for details.
