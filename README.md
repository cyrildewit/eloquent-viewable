# Laravel Page Visit Counter

[![StyleCI](https://styleci.io/repos/94131608/shield?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-visits-counter)
[![Total Downloads](https://img.shields.io/packagist/dt/cyrildewit/laravel-page-visits-counter.svg?style=flat-square)](https://packagist.org/packages/cyrildewit/laravel-page-visits-counter)

This package allows you to store page visits of different models into the database.

Once installed you can do stuff like this:

```php
// Return total visits of the article
$article->total_visits_count

// Return total visits of last 24 hours
$article->last_24h_visits_count

// Store new visit in the databae
$article->addVisit();

// Store new visit in the databae with expiry date
$article->addVisitThatExpiresAt(Carbon:now()->addHours(3));
```

This package is not built with the intent to collect analyticial data. It is made to simply save the visits of an Laravel model item. You would use our trait for models like `Task`, `Article`, `Post` or `Course`. But of course you can use this package as you want.

## Installation

This package can be used in Laravel 5.4 or higher.

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

First add the `Cyrildewit\PageVisitsCounter\Traits\HasPageVisitsCounter` trait to your visitable model(s).

```php
use Illuminate\Database\Eloquent\Model;
use Cyrildewit\PageVisitsCounter\Traits\HasPageVisitsCounter;

class Article extends Model
{
    use HasPageVisitsCounter;

    // ...
}
```

### Retrieving Page Visits Count

```php
// Return total number of visits of the article.
$article->total_visits_count

$article->last_24h_visits_count // Only in past 24 hours
$article->last_7d_visits_count  // Only in past 7 days
$article->last_14d_visits_count // Only in past 14 days

// Retrieve visits from date (past 2 weeks)
$article->retrievePageVisitsFrom(Carbon::now()->subWeeks(2));

// Retrieve visits between two dates
$article->retrievePageVisitsCountBetween(Carbon::now()->subMonths(1), Carbon::now()->subWeeks(1));
```

### Adding a new visit

```php
// Add one visit
$article->addVisit()

// Add one with expriy date
$article->addVisitThatExpiresAt(Carbon::now()->addHours(2))
```

### Configuring the formatted number format

It is very easy to change the format of the converted numbers. Simply change the three parameters of the official function [`number_format()`](http://php.net/manual/en/function.number-format.php).

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

Special thanks to [Freek Van der Herten](https://github.com/freekmurze) who inspired me to create opensource Laravel packages. Before creating this package I didn't had any experience with creating a Laravel Package and how to use create composer.json files. Because he created a lot of opensource packages I could learn how to do it. Make sure you take a look at [his open source packages](https://spatie.be/nl/opensource/laravel) as well!

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
