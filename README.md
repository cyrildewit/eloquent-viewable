# Eloquent Visitable

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/eloquent-visitable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-visitable)
[![Travis branch](https://img.shields.io/travis/cyrildewit/eloquent-visitable/master.svg?style=flat-square)](https://travis-ci.org/cyrildewit/eloquent-visitable)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://styleci.io/repos/94131608)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/eloquent-visitable.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/eloquent-visitable)
[![license](https://img.shields.io/github/license/cyrildewit/eloquent-visitable.svg?style=flat-square)](https://github.com/cyrildewit/eloquent-visitable/blob/master/LICENSE.md)

This Laravel >= 5 package allows you to add a visit counter to your visitable Eloquent models.

Once installed you can do stuff like this:

```php
// Get the total number of visits
$post->getVisitsCount();

// Get the total number of visits since a specific date
$post->getVisitsCountSince(Carbon::now()->subDays(7));

// Get the total number of visits upto a specific date
$post->getVisitsCountUpto(Carbon::now()->parse('01-04-2017'), Carbon::now()->parse('01-07-2017'));

// Add a new visit
$post->addVisit();
```

## Overview

Eloquent Visitable is a powerful, flexible and easy to use Laravel package for adding a page visit counter to your Eloquent models. It's designed to be flexible and useful for various projects.

This package is not built with the intent to collect analytical data. It is made to simply store the visits of a Laravel Eloquent model. You would use our trait for models like: `Post`, `Video`, `Course` and `Hotel`, but of course, you can use this package as you want.

### Features

Here are some of the main features:

* Store model visits
* Get the total number (unique) visits
* Get the total number (unique) visits since a specific date
* Get the total number (unique) visits upto a specific date
* Cache the retrieved visits counts
* Queue the visits before to prevent slow requests
* Configure date transformers to replace big lines like `$post->getVisitsCountSince(Carbon::now()->subDays(1));` to `$article->getVisitsCountSince('past24hours')` ('past24hours' is configured in the config).

Feature requests are very welcome! Create an issue with `[Feature Request]` as prefix.

## Documentation

In this documentation, you will find some helpful information about the use of this Laravel package. If you have any questions about this package or if you discover any security-related issues, then feel free to get in touch with me at `github@cyrildewit.nl`.

### Table of contents

1. [Getting Started](#getting-started)
    * [Requirements](#requirements)
    * [Installation](#installation)
2. [Usage](#usage)
    * [Making an Eloquent model visitable](#making-an-eloquent-model-visitable)
    * [Saving new visits](#saving-new-visits)
    * [Retrieving visits count](#retrieving-visits-count)
    * [Sorting Eloquent models by visits count](#sorting-eloquent-models-by-visits-count)
3. [Configuring](#configuring)
    * [Defining date transformers](#defining-date-transformers)
    * [Extending the Visit model](#extending-the-visit-model)
4. [Under the hood](#under-the-hood)
    * [List of properties/methods that the trait adds to your model](#list-of-propertiesmethods-that-the-trait-adds-to-your-model)

## Getting Started

### Requirements

This package requires [PHP](https://php.net/) v7+ and [Laravel](https://laravel.com/) 5.1+.

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
php artisan vendor:publish --provider="CyrildeWit\EloquentVisitable\EloquentVisitableServiceProvider" --tag="migrations"
```

After publishing the migration file you can create the `visits` table by running the migrations:

```winbatch
php artisan migrate
```

However, if you already have already a table named `visits`, you can this in the config. Search for 'table_names->visits' and change the value to something unique.

You can publish the config file with:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentVisitable\EloquentVisitableServiceProvider" --tag="config"
```

## Usage

In the following sections, you will find information about the usage of this package.

### Making an Eloquent model visitable

First add the `CyrildeWit\EloquentVisitable\Traits\Visitable` trait to your visitable Eloquent model(s). The trait will add some core functionality to get the visits count and save them.

Here's an example of an Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentVisitable\Traits\Visitable;

class Post extends Model
{
    use Visitable;

    // ...
}
```

**Tip!** To see which properties and method this trait adds to your model, look at the bottom of this documentation or [click here](#)!

### Saving new visits

After adding the trait to your model, some methods will be available. `addVisit()` is one of them. It will simply store a new visit in the database or queue. The best place where you should put it, is in your controller. If you're following the CRUD standard, it would be the `show` method.

Let's assume we're handling the visits of a post. `$post` contains an instance of our Eloquent model `App\Models\Post`.

```php
// Store a new visit in the databse or queue
$post->addVisit();
```

### Retrieving visits count

When retrieving visits count from the database, the values will be stored in the cache for a while. You can configure this in the config file.

**Note:** Unique visits are getting retrieved differently than the total visits. When calculating the total page views, we are using the aggregate functions of SQL. But the calculation of the unique page views is done by retrieving all the items and count them. If you're a SQL expert and know how to solve this, please send a PR! Thanks!

```php
// Retrieve the total (unique) visits
$post->getVisitsCount();
$post->getUniqueVisitsCount();

// Retrieve the total (unique) visits that are stored after the given date
$post->getVisitsCountSince(Carbon::now()->subWeeks(2)); // since two weeks ago
$post->getUniqueVisitsCountSince(Carbon::now()->subWeeks(2)); // since two weeks ago

// Retrieve the total (unique) visits that are stored before the given date
$post->getVisitsCountSince(Carbon::now()->subDays(3)); // upto three days ago
$post->getUniqueVisitsCountSince(Carbon::now()->subDays(3)); // upto three days ago

// Retrieve the total (unique) visits that are stored between the two given dates
$post->getVisitsCountSince(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1));
$post->getUniqueVisitsCountSince(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1));
```

### Sorting Eloquent models by visits count

In the following code, we're just using Laravels functionality.

```php
$posts = Post::all();

// Sort by most visits
$sortedPosts = $posts->sortByDesc(function ($post) {
    return $post->getVisitsCount();
})
```

## Configuring

### Defining date transformers

Because developers hate to repeat code, this package allows you to define date transformers. Let's say we're using the following code in our (blade) views a lot: `$post->getVisitsCountSince(Carbon::now()->subDays(4))`. It can get a little bit annoying and unreadable. Let's solve that by defining a date transformer for this.

If you have published the configuration file, you will find something like this:

```php
'date-transformers' => [
    // 'past24hours' => Carbon::now()->subDays(1),
    // 'past7days' => Carbon::now()->subWeeks(1),
    // 'past14days' => Carbon::now()->subWeeks(2),
],
```

They are all commented out as default. To make them available, simply uncomment them. The provided ones are serving as an example. You can remove them or add your own ones.

```php
'date-transformers' => [
    'past4days' => Carbon::now()->subDays(4),
],
```

We can now retrieve the visits like this in our (blade) views:

```html
<p>Total number of views in the past 4 days: {{ $article->getVisitsCountSince('past4days') }}</p>
```

### Extending the Visit model

If you need to extend or replace the existing PageView model you just need to keep the following thing in mind:

* Your `Visit` model needs to implement the `CyrildeWit\EloquentVisitable\Contracts\Models\Visit` contract.
* You can publish the configuration file with this command:

```winbatch
php artisan vendor:publish --provider="CyrildeWit\EloquentVisitable\EloquentVisitableServiceProvider" --tag="config"
```

And update the `visit` value under `models` in the configuration file.

## Under the hood

### List of properties/methods that the trait adds to your model

* `public function visits(): MorphMany;`
* `public function getVisitsCount();`
* `public function getVisitsCountSince($sinceDate): int;`
* `public function getVisitsCountUpto($uptoDate): int;`
* `public function getVisitsCountBetween($sinceDate, $uptoDate): int;`
* `public function getUniqueVisitsCount(): int;`
* `public function getUniqueVisitsCountSince($sinceDate): int;`
* `public function getUniqueVisitsCountUpto($uptoDate): int;`
* `public function getUniqueVisitsCountBetween($sinceDate, $uptoDate): int;`
* `public function addVisit(): bool;`

## Contributing

Please see the [CONTRIBUTING.md](CONTRIBUTING.md) file for more information about contributing and the project.

## Credits

* [Cyril de Wit](https://github.com/cyrildewit)
* [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
