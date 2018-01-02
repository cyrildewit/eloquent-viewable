# Laravel Page View Counter

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/laravel-page-view-counter.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-view-counter)
[![Travis branch](https://img.shields.io/travis/cyrildewit/laravel-page-view-counter/master.svg?style=flat-square)](https://travis-ci.org/cyrildewit/laravel-page-view-counter)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://styleci.io/repos/94131608)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/laravel-page-view-counter.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-view-counter)
[![license](https://img.shields.io/github/license/cyrildewit/laravel-page-view-counter.svg?style=flat-square)](https://github.com/cyrildewit/laravel-page-view-counter/blob/master/LICENSE.md)

This Laravel package allows you to store page views of different Eloquent models into the databse.

Once installed you can do stuff like this:

```php
// Get the total page views
$article->getPageViews();

// Get the total page from a specific date
$article->getPageViewsFrom(Carbon::now()->subDays(2));

// Get the total page of a specific date range
$article->getPageViewsBetween(Carbon::now()->parse('01-04-2017'), Carbon::now()->parse('01-06-2017'));

// Store a new page view into the database
$article->addPageView();

// Store a new page view into the database with an expiry date
$article->addPageViewThatExpiresAt(Carbon::now()->addHours(3));
```

This package is not built with the intent to collect analytical data. It is made to simply save the page views of a Laravel Eloquent model. You would use our trait for models like `Task`, `Article`, `Post` or `Course`. But of course, you can use this package as you want.

## Overview

Laravel Page View Counter is a powerful, flexible and easy to use Laravel package for adding a page view counter to your Eloquent models. It's designed to be flexible and useful for various projects. Instead of only a simple page view counter, we provide out of the box some great functionalities.

### Features

Here are some of the main features:

* Store page views
* Store page views with expiry dates (history is stored in the users session)
* Get the total page views
* Get the total page views from a specific date
* Get the total page views between a specific date range
* Get the total unique page views (by ip address)
* Configure date transformers to replace big lines like `$article->getPageViewsFrom(Carbon::now()->subDays(1))` to `$article->getPageViewsFrom('24h')` ('24h', '7d' etc. is completely configurable).

**Feature requests are very welcome! Create an issue with `[Feature Request]` as prefix**

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package. If you have any questions about this package or if you discover any security-related issues, then feel free to get in touch with me at github@cyrildewit.nl.

### Table of contents

1. [Getting Started](#getting-started)
    * [Requirements](#requirements)
    * [Branching Model](#branching-model)
    * [Installation](#installation)
2. [Usage](#usage)
    * [Making an Eloquent model viewable](#making-an-eloquent-model-viewable)
    * [Storing new page views](#storing-new-page-views)
    * [Retrieving page views](#retrieving-page-views)
    * [Sorting model items by page views](#sorting-model-items-by-page-views)
3. [Configuration](#configuration)
    * [Defining date transformers](#defining-date-transformers)
    * [Extending the PageView model](#extending-the-pageview-model)
4. [Under the hood](#under-the-hood)
    * [List of properties/methods that the trait adds to your model](#list-of-propertiesmethods-that-the-trait-adds-to-your-model)

## Getting Started

### Requirements

This package requires [PHP](https://php.net/) v7+ and Laravel 5.1+.

### Branching Model

This project uses the [Gitflow branching model](http://nvie.com/posts/a-successful-git-branching-model/).

* the **master** branch contains the latest **stable** version
* the **develop** branch contains the latest **unstable** development version
* all stable versions are tagged using [semantic versioning](https://semver.org/).

### Installation

Before you can use this package you have to install it with composer.

You can install the package via composer:

```winbatch
composer require cyrildewit/laravel-page-view-counter
```

Now add the service provider in `config/app.php` file, or if you're using Laravel >=5.5, this can be done via the automatic package discovery:

```php
'providers' => [
    // ...
    CyrildeWit\PageViewCounter\PageViewCounterServiceProvider::class,
];
```

You can publish the migration with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\PageViewCounter\PageViewCounterServiceProvider" --tag="migrations"
```

After publishing the migration file you can create the `page visits` table by running the migrations:

```winbatch
php artisan migrate
```

You can publish the config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\PageViewCounter\PageViewCounterServiceProvider" --tag="config"
```

## Usage

In the following sections, you will find information about the usage of this package.

### Making an Eloquent model viewable

First add the `CyrildeWit\PageViewCounter\Traits\HasPageViewCounter` trait to your viewable Eloquent model(s). The trait will add some core functionality to your model to get the page views count and store them.

Here's an example of a an Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\PageViewCounter\Traits\HasPageViewCounter;

class Article extends Model
{
    use HasPageViewCounter;

    // ...
}
```

**Tip!** To see which properties and methods this trait adds to your model look at the bottom of this documentation or [click here](#list-of-propertiesmethods-that-the-trait-adds)!

### Storing new page views

After adding the trait to your model, some methods will be available. `addPageView()` is one of them. It will simply store a new page view in the database. The best place where you should put it is in your controller. If you're following the CRUD standard, it would be the show method.

Let's assume where are handling the page views of an article in the following sections. `$article` contains an instance of our Eloquent model `Article`.

```php
// Stores a new page view in the database
$article->addPageView();
```

"But what if users are refreshing the page multiple times?" Well, then you could use the `addPageViewThatExpiresAt()` method. It accepts an expiry date. Only the page view after that expiry date will be stored. The expiry date will be stored in the user's session.

```php
// Store a new page view in the database with an expiry date.
// When storing it, it will first check if it hasn't been already viewed by the current user.
$article->addPageViewThatExpiresAt(Carbon::now()->addHours(2));
```

### Retrieving page views

**Note:** Unique page views are getting retrieved differently than the total page views. When calculating the total page views, we are using the aggregate functions of SQL. But the calculation of the unique page views is done by retrieving all the items and count them. If you're a SQL expert and would like to solve this, thanks!

```php
// Retrieve the total page views
$article->getPageViews();
$article->getUniquePageViews();

// Retrieve page views that are stored after the given date
$article->getPageViewsFrom(Carbon::now()->subWeeks(2)); // since two weeks ago
$article->getUniquePageViewsFrom(Carbon::now()->subWeeks(2)); // based upon ip address

// Retrieve page views that are stored before the given date
$article->getPageViewsBefore(Carbon::now()->subWeeks(2)); // upto two weeks ago
$article->getUniquePageViewsBefore(Carbon::now()->subWeeks(2)); // based upon ip address

// Retrieve page views that are stored between the given two dates
$article->getPageViewsBetween(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1));
$article->getUniquePageViewsBetween(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1)); // based upon ip address
```

### Sorting model items by page views

```php
$articles = Article::all(); // Laravel Collection instance

// Articles sorted on page views (most page views is on top)
$sortedArticles = $articles->sortByDesc(function ($article) {
    return $article->getPageViews();
});
```

If you're interested in a more cleaner way, you could create an attribute in your model and append it.

```php
class Article extends Model
{
    use HasPageViewCounter;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['page_views'];

    /**
     * Get the total page views of the article.
     *
     * @return int
     */
    public function getPageViewsAttribute()
    {
        return $this->getPageViews();
    }
}
```

You can access the total page views now by `$article->page_views`. This makes the following available:

```php
// Different examples
$articles = Article::all()->sortBy('page_views');
$articles = Article::with('relatedModel')->get()->sortBy('page_views');
$articles = Article::where('status', 'published')->get()->sortBy('page_views');
```

## Configuration

When published, the `config/page-view-counter.php` config file contains:

```php
use Carbon\Carbon;

return [

    /*
     * Our "HasPageViewCounter" trait needs to know which Eloquent model should
     * be used to retrieve your page views.
     *
     * The model you want to use as a PageView model needs to implement the
     * `CyrildeWit\PageViewCounter\Contracts\PageView` contract.
     */
    'page_view_model' => CyrildeWit\PageViewCounter\Models\PageView::class,

    /*
     * Our "HasPageViewCounter" trait needs to know which table should be used
     * to retrieve your page views.
     *
     * It is used by creating the migrations files and default Eloquent model.
     */
    'page_views_table_name' => 'page-views',

    /*
     * The below key is used by the PageViewHistory class that handles the page
     * views with expiry dates. Make sure that it's unique.
     */
    'page_view_history_session_key' => 'page-view-counter.session.history',

    /*
     * Register here your custom date transformers. When the package get one of
     * the below keys, it will use the value instead.
     *
     * Keep it empty, if you don't want any date transformers!
     *
     * Example:
     * - $article->getPageViewsFrom('past24hours'); // Get the total page views of the past 24 hours
     * - $article->getPageViewsFrom('past14days'); // Get the total page views of the past 14 days
     */
    'date-transformers' => [
        // 'past24hours' => Carbon::now()->subDays(1),
        // 'past7days' => Carbon::now()->subWeeks(1),
        // 'past14days' => Carbon::now()->subWeeks(2),
    ],

];
```

### Defining date transformers

Because we all love having to repeat less, this package allows you to define date transformers. Let's say we are using the following code a lot in our blade views: `$article->getPageViewsFrom(Carbon::now()->subDays(3))`. It can get a little bit annoying and unreadable. Let's solve that!

If you've published the configuration file, you will see something like this:

```php
'date-transformers' => [
    // 'past24hours' => Carbon::now()->subDays(1),
    // 'past7days' => Carbon::now()->subWeeks(1),
    // 'past14days' => Carbon::now()->subWeeks(2),
],
```

They are all commented out as default. To make them available, simply uncomment them. The provided ones are serving as an example. You can remove them or add your own ones.

For our example, we could do the following:

```php
'date-transformers' => [
    'past3days' => Carbon::now()->subDays(3),
],
```

We can now retrieve the page views like this in our blade views:

```html
<p>Page views in past three days {{ $article->getPageViewsFrom('past3days') }}</p>
```

### Extending the PageView model

If you need to extend or replace the existing PageView model you just need to keep the following thing in mind:

* Your `PageView` model needs to implement the `CyrildeWit\PageViewCounter\Contracts\PageView` contract.
* You can publish the configuration file with this command:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\PageViewCounter\PageViewCounterServiceProvider" --tag="config"
```

And update the `page_view_model` value.

## Under the hood

### List of properties/methods that the trait adds to your model

* `public function views();`
* `public function retrievePageViews();`
* `public function getPageViews();`
* `public function getPageViewsFrom();`
* `public function getPageViewsBefore();`
* `public function getPageViewsBetween();`
* `public function getUniquePageViews();`
* `public function getUniquePageViewsFrom();`
* `public function getUniquePageViewsBefore();`
* `public function getUniquePageViewsBetween();`
* `public function addPageView();`
* `public function addPageViewThatExpiresAt();`

## Credits

* [Cyril de Wit](https://github.com/cyrildewit)
* [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
