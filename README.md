# Laravel Page Visit Counter

[![Packagist](https://img.shields.io/packagist/v/cyrildewit/laravel-page-visits-counter.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-visits-counter)
[![Travis branch](https://img.shields.io/travis/cyrildewit/laravel-page-visits-counter/master.svg?style=flat-square)](https://travis-ci.org/cyrildewit/laravel-page-visits-counter)
[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-visits-counter)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/laravel-page-visits-counter.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-visits-counter)
[![license](https://img.shields.io/github/license/cyrildewit/laravel-page-visits-counter.svg?style=flat-square)](https://github.com/cyrildewit/laravel-page-visits-counter/blob/master/LICENSE.md)

This package allows you to store page visits of different models into the database.

Once installed you can do stuff like this:

```php
// Return total visits of the article
$article->total_visits_count->formatted

// Return total visits of last 24 hours
$article->last_24h_visits_count->formatted

// Store new visit in the databae
$article->addVisit();

// Store new visit in the databae with expiry date
$article->addVisitThatExpiresAt(Carbon::now()->addHours(3));
```

This package is not built with the intent to collect analyticial data. It is made to simply save the visits of an Laravel model item. You would use our trait for models like `Task`, `Article`, `Post` or `Course`. But of course you can use this package as you want.

## Overview

Laravel Page Visits Counter is a powerful, flexible and lightweight Laravel package for adding a page view counter to your Eloquent models. It's designed to be flexible and useful for various projects. Instead of only a simple visits counter we provide out of the box some great functionalities.

### Features

Here are some of the main features of Laravel Page Visits Counter:

* Add a new visit.
* Add a new visit with expiry date (history is stored in the session).
* Get the total visits.
* Get the total visits from the past: 24 hours, 7 days or 14 days.
* Retrieve the visits count formatted like 120.000 instead of 120000 (great for blade views)

## Documentation

In this documention you will find some helpful information about the use of this Laravel package. If you have any questions about this package or if you discover any security related issues, then feel free to get in touch with me at: info(at)cyrildewit.nl.

**In this documention:**

1. [Getting Started](#getting-started)
2. [Usage](#usage)
    * [Making a Elqouent model visitable](#making-a-eloquent-model-visitable)
    * [Retrieving page visits count](#retrieving-page-visits-count)
    * [Storing new visits](#storing-new-visits)
3. [Configuration](#configuration)
    * [Configuring the formatted number format](#configuring-the-formatted-number-format)

## Getting Started

Before you can use this package you have to install it with composer ;).

You can install the package via composer:
```winbatch
composer require cyrildewit/laravel-page-visits-counter
```

Now add the service provider in `config/app.php` file:

```php
'providers' => [
    // ...
    Cyrildewit\PageVisitsCounter\PageVisitsCounterServiceProvider::class,
];
```

You can publish the migration with:

```winbatch
php artisan vendor:publish --provider="Cyrildewit\PageVisitsCounter\PageVisitsCounterServiceProvider" --tag="migrations"
```

After publishing the migration file you can create the `page visits` table by running the migrations:

```winbatch
php artisan migrate
```

You can publish the config file with:

```winbatch
php artisan vendor:publish --provider="Cyrildewit\PageVisitsCounter\PageVisitsCounterServiceProvider" --tag="config"
```

## Usage

In the following sections you will find information about the usage of this package.

### Making a Elqouent model visitable

First add the `Cyrildewit\PageVisitsCounter\Traits\HasPageVisitsCounter` trait to your visitable Eloquent model(s).

Here's an example of a an Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use Cyrildewit\PageVisitsCounter\Traits\HasPageVisitsCounter;

class Article extends Model
{
    use HasPageVisitsCounter;

    // ...
}
```

### Retrieving page visits count

Our attributes always return an object. This object contains two properties: `number` & `formatted`. As the names maybe suggests `total_visits_count->number` will return the whole number and `total_visits_count->formatted` will return a formatted string (120000 -> 120.000).

```php
$article->total_visits_count    // Retrieve all counted visits
$article->last_24h_visits_count // Retrieve all counted visits from the past 24 hours
$article->last_7d_visits_count  // Retrieve all counted visits from the past 7 days
$article->last_14d_visits_count // Retrieve all counted visits from the past 14 days

// Retrieve visits from date (past 2 weeks)
$article->retrievePageVisitsFrom(Carbon::now()->subWeeks(2));

// Retrieve visits between two dates
$article->retrievePageVisitsCountBetween(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1));
```

### Storing new visits

```php
// Stores a new visit into the database
$article->addVisit()

// Store a new visit into the database with expiry date.
// When storing it, it will first checks if it's not already have been viewed by the current user.
$article->addVisitThatExpiresAt(Carbon::now()->addHours(2))
```

## Configuration

### Configuring the formatted number format

It is very easy to change the format of the converted numbers. Simply change the three parameters of the official PHP function [`number_format()`](http://php.net/manual/en/function.number-format.php).

In `config/page-visit-counter.php` you will find the following code:

```php
return [

    // ...

    'output-settings' => [

        /*
         * Set true for aut number output.
         */
        'formatted-output-enabled' => true,

        /*
         * The following optiosn will be used inside the
         * `number_format`function.
         * Example: 120000    ==> 120.000
         * Example: 500206000 ==> 502.006.000
         */
        'format-options' => [

            'decimals' => 0,
            'dec_point' => ',',
            'thousands_sep' => '.',

        ],

    ],

]
```

## Credits

- [Cyril de Wit](https://github.com/cyrildewit)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
