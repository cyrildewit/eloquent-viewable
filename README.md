<div align="center">
  <a href="https://github.com/cyrildewit/eloquent-viewable">
    <img src="/art/logo.png" alt="Eloquent Viewable Logo" width="80" height="80">
  </a>
  <h3 align="center">Eloquent Viewable</h3>
  <p align="center">
    A minimalistic analytics package for Laravel with seamless view tracking for Eloquent models
  </p>
  <br>
  <p align="center">
    <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable"><img alt="Latest Version" src="https://img.shields.io/packagist/v/cyrildewit/eloquent-viewable"/></a>        
      <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable="><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/cyrildewit/eloquent-viewable"/></a>
      <a href="https://github.com/cyrildewit/eloquent-viewable/actions"><img alt="GitHub Actions Workflow Status" src="https://img.shields.io/github/actions/workflow/status/cyrildewit/eloquent-viewable/tests.yml?branch=8.x&label=Tests 8.x"/></a>
      <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable"><img alt="License" src="https://img.shields.io/packagist/l/cyrildewit/eloquent-viewable"/></a>
      <a href="https://codecov.io/gh/cyrildewit/eloquent-viewablee"><img alt="License" src="https://img.shields.io/codecov/c/github/cyrildewit/eloquent-viewable/8.x.svg"/></a>
  </p>
</div>

---

**Eloquent Viewable** is a minimalistic and flexible analytics package for Laravel that enables seamless tracking of
views for your Eloquent models. Whether you’re building a blog, an e-commerce platform, or any other application that
benefits from tracking user interactions, this package provides a way to monitor views directly within your
Laravel project without needing third-party analytics services.

### Quick Example

Once installed, you can track and retrieve views effortlessly:

```php
// Return total views count
views($post)->count();

// Return total views count that have been made since 20 February 2017
views($post)->period(Period::since('2017-02-20'))->count();

// Return total unique views count (based on visitor cookie)
views($post)->unique()->count();

// Record a view
views($post)->record();

// Record a view with a cooldown period
views($post)->cooldown(now()->addHours(2))->record();
```

### Features

* Associate views with Eloquent models
* Get total views count
* Get views count of a specific period
* Get unique views count
* Get views count of a viewable type (Eloquent model class)
* Order viewables by views
* Set a cooldown between views
* Elegant cache wrapper built-in
* Ignore views from crawlers, ignored IP addresses or requests with DNT header

## Introduction

Tracking views is more than just incrementing a counter — it requires a thoughtful approach to ensure accuracy, prevent
artificial inflation, and allow for meaningful analytics. Eloquent Viewable takes a flexible and database-driven
approach to view tracking, giving you control over how views are recorded, counted, and retrieved.

### Design Approach

**1. Persistent View Logging**

Unlike simplistic counters, this package logs each view as a separate database record. This allows for powerful queries,
such as filtering views by time range, visitor uniqueness, or any other stored metadata.

```php
views($post)->period(Period::create('2018-01-10', '2018-02-17'))->count();
```

**2. Context-Aware Visitor Identification & Cooldown Protection**

To prevent inflated view counts, Eloquent Viewable uses a long-lived cookie by default to identify unique visitors. This
enables deduplication, ensuring that repeated page refreshes do not artificially increase the view count.

Additionally, the cooldown feature prevents multiple views from being recorded within a specified time frame. By
default, this uses the session storage to track when a visitor last viewed a model. If a visitor revisits within the
cooldown period, the view won’t be recorded again.

```php
views($post)->cooldown(now()->addHours(2))->record();
```

**3. Optimized for Performance with Built-in Caching**

Since logging every view individually can lead to database growth, this package includes an elegant caching solution to
reduce query load. Instead of querying raw view records every time, you can cache the results:

```php
views($post)->remember(180)->count();
```

**4. Ignoring Views from Crawlers & Unwanted Requests**

To prevent misleading statistics, Eloquent Viewable automatically ignores views from web crawlers, ignored IP addresses,
and users with the “Do Not Track” (DNT) header enabled.

```php
// Configure ignored crawlers, IPs, and DNT settings in config/eloquent-viewable.php
'ignore_crawlers' => true,
'ignored_ip_addresses' => ['192.168.1.1', '10.0.0.1'],
'respect_dnt_header' => true,
```

**5. Evaluating the Trade-offs**

The main advantage of this approach is its granularity—you get precise, configurable view tracking with powerful query
capabilities. However, since every view is stored, applications with extremely high traffic may need to consider:

- **Database maintenance** — Table partitioning, periodic pruning or archival of old view records
- **Cache tuning** – Adjusting caching strategies based on performance needs

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package.

### Table of contents

1. [Getting Started](#getting-started)
    * [Version Compatibility](#version-compatibility)
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
        * [Order by views count](#order-by-views-count)
        * [Order by unique views count](#order-by-unique-views-count)
        * [Order by views count within the specified period](#order-by-views-count-within-the-specified-period)
        * [Order by views count within the specified collection](#order-by-views-count-within-the-specified-collection)
    * [Get views count of viewable type](#get-views-count-of-viewable-type)
    * [View collections](#view-collections)
    * [Remove views on delete](#remove-views-on-delete)
    * [Caching view counts](#caching-view-counts)
3. [Optimizing](#optimizing)
    * [Database indexes](#database-indexes)
    * [Caching](#caching)
4. [Extending](#extending)
    * [Custom information about visitor](#custom-information-about-visitor)
    * [Using your own Views Eloquent model](#using-your-own-views-eloquent-model)
    * [Using your own View Eloquent model](#using-your-own-view-eloquent-model)
    * [Using a custom crawler detector](#using-a-custom-crawler-detector)
    * [Adding macros to the Views class](#adding-macros-to-the-views-class)

## Getting Started

#### Version Compatibility

| Version | Laravel        | PHP Version |
|---------|----------------|-------------|
| ^7.0    | 6.x.x - 12.x.x | >= 7.4.0    |

Support for Lumen is not maintained.

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
* **Trait:** `CyrildeWit\EloquentViewable\InteractsWithViews`

Example:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class Post extends Model implements Viewable
{
    use InteractsWithViews;

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

**Note:** This package filters out crawlers by default. Be aware of this when testing, because Postman is for example
also a crawler.

### Setting a cooldown

You may use the `cooldown` method on the `Views` instance to add a cooldown between view records. When you set a
cooldown, you need to specify the number of minutes.

```php
views($post)
    ->cooldown($minutes)
    ->record();
```

Instead of passing the number of minutes as an integer, you can also pass a `DateTimeInterface` instance.

```php
$expiresAt = now()->addHours(3);

views($post)
    ->cooldown($expiresAt)
    ->record();
```

#### How it works

When recording a view with a session delay, this package will also save a snapshot of the view in the visitor's session
with an expiration datetime. Whenever the visitor views the item again, this package will checks his session and decide
if the view should be saved in the database or not.

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

The `Period` class that comes with this package provides many handy features. The API of the `Period` class looks as
follows:

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

If you want to know how many views a specific viewable type has, you need to pass an empty Eloquent model to the
`views()` helper like so:

```php
views(new Post())->count();
```

You can also pass a fully qualified class name. The package will then resolve an instance from the application
container.

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

To automatically delete all views of an viewable Eloquent model on delete, you can enable it by setting the
`removeViewsOnDelete` property to `true` in your model definition.

```php
protected $removeViewsOnDelete = true;
```

### Caching view counts

Caching the views count can be challenging in some scenarios. The period can be for example dynamic which makes caching
not possible. That's why you can make use of the in-built caching functionality.

To cache the views count, simply add the `remember()` method to the chain. The default lifetime is forever.

Examples:

```php
views($post)->remember()->count();
views($post)->period(Period::create('2018-01-24', '2018-05-22'))->remember()->count();
views($post)->period(Period::upto('2018-11-10'))->unique()->remember()->count();
views($post)->period(Period::pastMonths(2))->remember()->count();
views($post)->period(Period::subHours(6))->remember()->count();
```

```php
// Cache for 3600 seconds
views($post)->remember(3600)->count();

// Cache until the defined DateTime
views($post)->remember(now()->addWeeks(2))->count();

// Cache forever
views($post)->remember()->count();
```

## Optimizing

### Database indexes

The default `views` table migration file has already two indexes for `viewable_id` and `viewable_type`.

If you have enough storage available, you can add another index for the `visitor` column. Depending on the amount of
views, this may speed up your queries in some cases.

### Caching

Caching views counts can have a big impact on the performance of your application. You can read the documentation about
caching the views count [here](#caching-view-counts)

Using the `remember()` method will only cache view counts made by the `count()` method. The `orderByViews` and
`orderByUnique` query scopes aren't using these values because they only add something to the query builder. To optimize
these queries, you can add an extra column or multiple columns to your viewable database table with these counts.

Example: we want to order our blog posts by **unique views** count. The first thing that may come to your mind is to use
the `orderByUniqueViews` query scope.

```php
$posts = Post::latest()->orderByUniqueViews()->paginate(20);
```

This query is quite slow when you have a lot of views stored. To speed things up, you can add for example a
`unique_views_count` column to your `posts` table. We will have to update this column periodically with the unique views
count. This can easily be achieved using a schedued Laravel command.

There may be a faster way to do this, but such command can be like:

```php
$posts = Post::all();

foreach($posts as $post) {
    $post->unique_views_count = views($post)->unique()->count();
}
```

## Extending

If you want to extend or replace one of the core classes with your own implementations, you can override them:

* `CyrildeWit\EloquentViewable\Views`
* `CyrildeWit\EloquentViewable\View`
* `CyrildeWit\EloquentViewable\Visitor`
* `CyrildeWit\EloquentViewable\CrawlerDetectAdapter`

_**Note:** Don't forget that all custom classes must implement their original interfaces_

### Custom information about visitor

The `Visitor` class is responsible for providing the `Views` builder information about the current visitor. The
following information is provided:

* a unique identifier (stored in a cookie)
* ip address
* check for Do No Track header
* check for crawler

The default `Visitor` class gets its information from the request. Therefore, you may experience some issues when using
the `Views` builder via a RESTful API. To solve this, you will need to provide your own data about the visitor.

You can override the `Visitor` class globally or locally.

#### Create your own `Visitor` class

Create you own `Visitor` class in your Laravel application and implement the
`CyrildeWit\EloquentViewable\Contracts\Visitor` interface. Create the required methods by the interface.

Alternatively, you can extend the default `Visitor` class that comes with this package.

#### Globally

Simply bind your custom `Visitor` implementation to the `CyrildeWit\EloquentViewable\Contracts\Visitor` contract.

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\Visitor::class,
    \App\Services\Views\Visitor::class
);
```

#### Locally

You can also set the visitor instance using the `useVisitor` setter method on the `Views` builder.

```php
use App\Services\Views\Visitor;

views($post)
    ->useVisitor(new Visitor()) // or app(Visitor::class)
    ->record();
```

### Using your own `Views` Eloquent model

Bind your custom `Views` implementation to the `\CyrildeWit\EloquentViewable\Contracts\Views`.

Change the following code snippet and place it in the `register` method in a service provider (for example
`AppServiceProvider`).

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\Views::class,
    \App\Services\Views\Views::class
);
```

### Using your own `View` Eloquent model

Bind your custom `View` implementation to the `\CyrildeWit\EloquentViewable\Contracts\View`.

Change the following code snippet and place it in the `register` method in a service provider (for example
`AppServiceProvider`).

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Contracts\View::class,
    \App\Models\View::class
);
```

### Using a custom crawler detector

Bind your custom `CrawlerDetector` implementation to the `\CyrildeWit\EloquentViewable\Contracts\CrawlerDetector`.

Change the following code snippet and place it in the `register` method in a service provider (for example
`AppServiceProvider`).

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Contracts\CrawlerDetector::class,
    \App\Services\Views\CustomCrawlerDetectorAdapter::class
);
```

### Adding macros to the `Views` class

```php
use CyrildeWit\EloquentViewable\Views;

Views::macro('countAndRemember', function () {
    return $this->remember()->count();
});
```

Now you're able to use this shorthand like this:

```php
views($post)->countAndRemember();

Views::forViewable($post)->countAndRemember();
```

## Upgrading

Please see [UPGRADING](UPGRADING.md) for detailed upgrade guide.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

* **Cyril de Wit** - _Initial work_ - [cyrildewit](https://github.com/cyrildewit)

See also the list of [contributors](https://github.com/cyrildewit/eloquent-viewable/graphs/contributors) who
participated in this project.

**Helpful Resources:**

* [Implementing A Page View Counter In Laravel](https://stidges.com/implementing-a-page-view-counter-in-laravel) - *
  *[Stidges](https://github.com/stidges)**

## Alternatives

* [antonioribeiro/tracker](https://github.com/antonioribeiro/tracker)
* [foothing/laravel-simple-pageviews](https://github.com/foothing/laravel-simple-pageviews)
* [awssat/laravel-visaits](https://github.com/awssat/laravel-visits)
* [Kryptonit3/Counter](https://github.com/Kryptonit3/Counter)
* [fraank/ViewCounter](https://github.com/fraank/ViewCounter)

Feel free to add more alternatives!

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
