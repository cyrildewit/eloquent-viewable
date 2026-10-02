<div align="center">
  <a href="https://github.com/cyrildewit/eloquent-viewable">
    <img src="art/logo.png" alt="Eloquent Viewable Logo" width="80" height="80">
  </a>
  <h3 align="center">Eloquent Viewable</h3>
  <p align="center">
    A minimalistic analytics package for Laravel with seamless view tracking for Eloquent models
  </p>
  <br/>
  <p align="center">
    <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable"><img alt="Latest Version" src="https://img.shields.io/packagist/v/cyrildewit/eloquent-viewable"/></a>        
      <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/cyrildewit/eloquent-viewable"/></a>
      <a href="https://github.com/cyrildewit/eloquent-viewable/actions"><img alt="GitHub Actions Workflow Status" src="https://img.shields.io/github/actions/workflow/status/cyrildewit/eloquent-viewable/tests.yml?label=Tests"/></a>
      <a href="https://packagist.org/packages/cyrildewit/eloquent-viewable"><img alt="License" src="https://img.shields.io/packagist/l/cyrildewit/eloquent-viewable"/></a>
      <a href="https://codecov.io/gh/cyrildewit/eloquent-viewable"><img alt="Coverage" src="https://img.shields.io/codecov/c/github/cyrildewit/eloquent-viewable.svg"/></a>
  </p>
</div>
<hr/>
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#introduction">Introduction</a></li>
    <li><a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#version-compatibility">Version Compatibility</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a>
      <ul>
        <li><a href="#preparing-your-model">Preparing your model</a></li>
        <li><a href="#recording-views">Recording views</a></li>
        <li><a href="#queueing-view-recording">Queueing view recording</a></li>
        <li><a href="#setting-a-cooldown">Setting a cooldown</a></li>
        <li><a href="#retrieving-view-counts">Retrieving view counts</a>
          <ul>
            <li><a href="#get-total-view-count">Get total view count</a></li>
            <li><a href="#get-view-count-for-a-specific-period">Get view count for a specific period</a>
            </li>
            <li><a href="#get-view-counts-grouped-by-interval">Get view counts grouped by interval</a></li>
            <li><a href="#get-unique-view-count">Get unique view count</a></li>
          </ul>
        </li>
        <li><a href="#ordering-models-by-view-count">Ordering models by view count</a>
          <ul>
            <li><a href="#order-by-view-count">Order by view count</a></li>
            <li><a href="#order-by-unique-view-count">Order by unique view count</a></li>
            <li><a href="#order-by-view-count-within-the-specified-period">Order by view count within the
              specified period</a></li>
            <li><a href="#order-by-view-count-within-the-specified-collection">Order by view count within
              the specified collection</a></li>
          </ul>
        </li>
        <li><a href="#get-view-count-of-viewable-type">Get view count of viewable type</a></li>
        <li><a href="#view-collections">View collections</a></li>
        <li><a href="#remove-views-on-delete">Remove views on delete</a></li>
        <li><a href="#caching-view-counts">Caching view counts</a></li>
      </ul>
    </li>
    <li><a href="#samples">Samples</a></li>
    <li><a href="#testing">Testing</a></li>
    <li><a href="#optimizing">Optimizing</a>
      <ul>
        <li><a href="#database-indexes">Database indexes</a></li>
        <li><a href="#caching">Caching</a></li>
      </ul>
    </li>
    <li><a href="#extending">Extending</a>
      <ul>
        <li><a href="#custom-information-about-visitor">Custom information about visitor</a></li>
        <li><a href="#using-your-own-views-eloquent-model">Using your own Views Eloquent model</a></li>
        <li><a href="#using-your-own-view-eloquent-model">Using your own View Eloquent model</a></li>
        <li><a href="#customizing-how-views-are-created">Customizing how views are created</a></li>
        <li><a href="#choosing-where-views-are-stored">Choosing where views are stored</a></li>
        <li><a href="#adding-a-recording-guard">Adding a recording guard</a></li>
        <li><a href="#customizing-how-views-are-counted">Customizing how views are counted</a></li>
        <li><a href="#adding-a-bucket-grammar-for-another-database-driver">Adding a bucket grammar for another database driver</a></li>
        <li><a href="#using-a-custom-crawler-detector">Using a custom crawler detector</a></li>
        <li><a href="#adding-macros-to-the-views-class">Adding macros to the Views class</a></li>
      </ul>
    </li>
    <li><a href="#upgrading">Upgrading</a></li>
    <li><a href="#changelog">Changelog</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#credits">Credits</a></li>
  </ol>
</details>

## Introduction

**Eloquent Viewable** is a flexible and minimalistic analytics package for Laravel that allows seamless tracking of
views for Eloquent models. Rather than incrementing a single counter, it stores each view as its own database record,
so you can analyze totals, unique visitors, and custom time periods entirely within your own application. Whether
you're running a blog, an e-commerce store, or a custom Laravel application, this package lets you log and analyze
views without relying on external analytics services.

### Quick Example

Once installed, you can track and retrieve views effortlessly:

```php
// Return total view count
views($post)->count();

// Return total unique view count since 20 February 2017
views($post)->unique()->period(Period::since('2017-02-20'))->count();

// Record a view
views($post)->record();
```

### Key Features

- Track **total** and **unique** views for any Eloquent model
- Query views by custom date ranges or time periods
- Prevent duplicate views with a configurable **cooldown system**
- Order models by views and unique visitors
- Optimize performance with **built-in caching**
- Ignore views from **crawlers, blocked IPs, and visitors who opt out** with Do Not Track or Global Privacy Control

## Getting Started

### Version Compatibility

| Package Version                                                            | Laravel    | PHP  |
|----------------------------------------------------------------------------|------------|------|
| [8.x](https://packagist.org/packages/cyrildewit/eloquent-viewable#8.x-dev) | 13.x       | 8.5+ |
| [7.x](https://packagist.org/packages/cyrildewit/eloquent-viewable#7.x-dev) | 6.x – 13.x | 7.4+ |

Support for Lumen is not maintained.

### Installation

First, you need to install the package via Composer:

```bash
composer require cyrildewit/eloquent-viewable:^8
```

Publish the database migrations and review them:

```bash
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
```

Run the database migrations to create the necessary tables:

```bash
php artisan migrate
```

You can optionally publish the config file:

```bash
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="config"
```

## Usage

### Preparing your model

To associate views with a model, the model **must** implement the following interface and trait:

- **Interface:** `CyrildeWit\EloquentViewable\Contracts\Viewable`
- **Trait:** `CyrildeWit\EloquentViewable\Concerns\InteractsWithViews`

Example:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class Post extends Model implements Viewable
{
    use InteractsWithViews;

    // ...
}
```

### Recording views

To track a view, simply call the `record` method on the fluent `Views` instance:

```php
views($post)->record();
```

**Where Should You Record Views?**

The recommended place to record views is inside your controller’s method that handles displaying the model. For example:

```php
// PostController.php
public function show(Post $post)
{
    views($post)->record();

    return view('post.show', compact('post'));
}
```

This ensures that views are only recorded when the page is actually rendered for a user.

Every call to `record()` passes a list of guards before anything is written. The list lives under `recording.guards`
in the config file and is the only switch: a guard runs when it is listed and not otherwise. Out of the box bot traffic
and the addresses in `recording.ignored_ip_addresses` are dropped and cooldowns are enforced. Publish the config and
uncomment `IgnoreDoNotTrack` or `IgnoreGlobalPrivacyControl` to honour those headers, or remove a guard to turn its
check off. Or add a guard of your own, see [Adding a recording guard](#adding-a-recording-guard).

> [!NOTE]
> `IgnoreCrawlers` is listed by default, so keep it in mind when testing. Tools like **Postman** are often detected as
> crawlers and will not trigger a recorded view.

When a guard refuses, the package dispatches `Recording\Events\ViewSkipped` with the attempt and the guard. Listen
for it to find out why a count stays where it is:

```php
use CyrildeWit\EloquentViewable\Recording\Events\ViewSkipped;

Event::listen(ViewSkipped::class, function (ViewSkipped $event): void {
    Log::debug('View skipped by '.$event->guard::class, [
        'viewable' => $event->attempt->viewable->getKey(),
        'collection' => $event->attempt->collection,
    ]);
});
```

### Queueing view recording

By default, views are stored during the request. On high-traffic pages you can defer the
database write to a queued job instead. This keeps the request fast and moves the insert to
a queue worker.

Queue an individual view on the fly using the `queue()` method:

```php
views($post)->queue()->record();
```

Or enable queueing globally in the `eloquent-viewable.php` config file:

```php
'recording' => [
    'queue' => [
        'enabled' => true,      // queue every recorded view
        'connection' => null,   // null uses the default queue connection
        'queue' => null,        // null uses the connection's default queue
    ],
],
```

When queueing is enabled globally, you can still force an individual view to be recorded
synchronously:

```php
views($post)->queue(false)->record();
```

All filtering still runs during the request, including crawler detection, the Do Not Track
header, ignored IP addresses and cooldowns. Bots and views on cooldown are therefore never
queued; only the database write is deferred.

Queueing defers the write, and so does a store that buffers views before landing them in the
table. Combining the two is harmless but gains nothing: every view becomes a job whose only
work is handing the record to the buffer. With a buffering store, leave `recording.queue.enabled` off.

> [!WARNING]  
> When a view is queued, the `ViewRecorded` event is dispatched from the queue worker
> instead of the request. Its listeners therefore run **without request context**. The
> session, cookies, `request()` and `auth()->user()` are unavailable and will return empty
> or `null` values. If a listener needs request-derived data (such as the authenticated
> user or the IP address), capture it during the request instead of reading it inside the
> listener. The event carries the `ViewRecord` that was recorded, under `$event->record`.

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

When recording a view with a session delay, this package also saves a snapshot of the view in the visitor’s session with
an expiration datetime. Whenever the visitor views the item again, the package checks their session and decides whether
the view should be saved in the database.

### Retrieving view counts

#### Get total view count

```php
views($post)->count();
```

#### Get view count for a specific period

```php
use CyrildeWit\EloquentViewable\Support\Period;

// Example: get view count from 2017 up to 2018
views($post)
    ->period(Period::create('2017', '2018'))
    ->count();
```

The `Period` class that comes with this package provides many handy features. The API of the `Period` class looks as
follows:

A period is half-open: the start is included and the end is excluded. `Period::create('2018-01-01', '2018-02-01')`
covers all of January and nothing of February.

##### Specifying a date range

```php
$startDateTime = Carbon::createFromDate(2017, 4, 12);
$endDateTime = '2017-06-12';

Period::create($startDateTime, $endDateTime);
```

##### Since a specific date

```php
Period::since(Carbon::create(2017));
```

##### Up to, but not including, a specific date

```php
Period::upto(Carbon::createFromDate(2018, 6, 1));
```

##### For past period

Uses `Carbon::today()` as start datetime minus the given unit.

```php
Period::pastDays(int $days);
Period::pastWeeks(int $weeks);
Period::pastMonths(int $months);
Period::pastYears(int $years);
```

##### For custom time subtraction

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

##### Timezones

`viewed_at` is stored as the wall clock of your application timezone. Period bounds use that same zone, and bounds
you pass in another timezone are converted before they are compared. Keep `app.timezone` at `UTC`, Laravel's default,
unless you have a reason not to.

#### Get view counts grouped by interval

`countByInterval()` returns the view count per hour, day, week, month or year over the period, with buckets that have
no views filled in with zero. It needs a period with a start date.

```php
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;

$series = views($post)
    ->period(Period::pastDays(30))
    ->countByInterval(Granularity::Day);

foreach ($series as $bucket) {
    $bucket->start; // Carbon, the start of the bucket
    $bucket->end;   // Carbon, the start of the next bucket
    $bucket->count; // int
}

$series->total();  // the same number as views($post)->period(Period::pastDays(30))->count()
$series->intervals; // Collection<int, Bucket>
```

Buckets are calendar-aligned, so the first one may start before the period, and weeks start on Monday. A bucket is
half-open like a period, so drilling into one gives the same count:

```php
views($post)->period($bucket->period())->count(); // === $bucket->count
```

`unique()`, `collection()` and `remember()` work as they do for `count()`:

```php
views($post)->period(Period::pastMonths(6))->unique()->countByInterval(Granularity::Month);
views(Post::class)->period(Period::pastWeeks(12))->collection('homepage')->countByInterval(Granularity::Week);
```

The database does the grouping, so the package ships a grammar per driver: SQLite, MySQL, MariaDB and Postgres. Any
other driver throws `UnsupportedDriver` until you
[register a grammar](#adding-a-bucket-grammar-for-another-database-driver) for it.

A call that would produce more than `querying.max_intervals` buckets (10,000 by default, configurable) throws
`InvalidInterval` before the database is queried.

#### Get unique view count

If you only want to retrieve the unique view count, you can simply add the `unique` method to the chain.

```php
views($post)
    ->unique()
    ->count();
```

### Ordering models by view count

The `Viewable` trait adds two scopes to your model: `orderByViews` and `orderByUniqueViews`.

#### Order by view count

```php
Post::orderByViews()->get(); // descending
Post::orderByViews('asc')->get(); // ascending
```

#### Order by unique view count

```php
Post::orderByUniqueViews()->get(); // descending
Post::orderByUniqueViews('asc')->get(); // ascending
```

#### Order by view count within the specified period

```php
Post::orderByViews('asc', Period::pastDays(3))->get();  // ascending
Post::orderByViews('desc', Period::pastDays(3))->get(); // descending
```

And of course, it's also possible with the unique views variant:

```php
Post::orderByUniqueViews('asc', Period::pastDays(3))->get();  // ascending
Post::orderByUniqueViews('desc', Period::pastDays(3))->get(); // descending
```

#### Order by view count within the specified collection

```php
Post::orderByViews('asc', null, 'custom-collection')->get();  // ascending
Post::orderByViews('desc', null, 'custom-collection')->get(); // descending

Post::orderByUniqueViews('asc', null, 'custom-collection')->get();  // ascending
Post::orderByUniqueViews('desc', null, 'custom-collection')->get(); // descending
```

### Get view count of viewable type

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

To retrieve the view count in a specific collection, you can reuse the same `collection()` method.

```php
views($post)
    ->collection('customCollection')
    ->count();
```

### Remove views on delete

When a viewable model is deleted, the package deletes its views with it. To keep the views, override
`shouldRemoveViewsOnDelete()` in your model.

```php
public function shouldRemoveViewsOnDelete(): bool
{
    return false;
}
```

A soft delete leaves the views in place, so a restored model still has its view count. Only `forceDelete()` removes
them. If you want to drop the views of a soft-deleted model anyway, call `views($post)->destroy()` yourself.

If your custom `View` model uses `SoftDeletes`, a force delete of the viewable soft deletes its views instead of
removing the rows.

A view that is queued or buffered when the model is force deleted can still be written afterwards, because the
delete only removes what is already stored. The row is harmless: nothing counts views for a model that no longer
exists, and keys are not reused.

### Caching view counts

Caching the view count can be challenging in some scenarios. The period can be for example dynamic which makes caching
not possible. That's why you can make use of the in-built caching functionality.

To cache the view count, simply add the `remember()` method to the chain. The default lifetime is forever.

Examples:

```php
views($post)->remember()->count();
views($post)->period(Period::create('2018-01-24', '2018-05-22'))->remember()->count();
views($post)->period(Period::upto('2018-11-10'))->unique()->remember()->count();
views($post)->period(Period::pastMonths(2))->remember()->count();
views($post)->period(Period::subHours(6))->remember()->count();
views($post)->period(Period::pastDays(30))->remember()->countByInterval(Granularity::Day);
```

```php
// Cache for 3600 seconds
views($post)->remember(3600)->count();

// Cache until the defined DateTime
views($post)->remember(now()->addWeeks(2))->count();

// Cache forever
views($post)->remember()->count();
```

## Samples

The [`samples`](samples) directory has real-world scenarios that combine several features, such as a
[trending articles](samples/TrendingArticles) list, a [stats page](samples/ListingStats) for one listing or a
[most viewed](samples/PopularProducts) sort over a large catalog. Each sample is tested with the rest of the suite.

## Testing

`Views::fake()` swaps the store and the source for one in-memory fake, so a test can record views without a `views`
table and read them back through the same `views()` calls. Call it before the code under test runs.

```php
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

it('records a view of the post', function (): void {
    $fake = Views::fake();

    $this->get(route('posts.show', $post));

    $fake->assertRecorded($post);
    $fake->assertRecorded($post, 1);
    $fake->assertRecorded($post, fn (ViewRecord $record): bool => $record->collection === 'sidebar');
    $fake->assertNotRecorded($otherPost);
    $fake->assertNothingRecorded();
    $fake->assertForgotten($post);
});
```

`recorded($post)` returns the matching `ViewRecord` objects as a collection. A viewable without a key, such as
`new Post`, stands for every viewable of its type.

The guards you list still run, so with `IgnoreCrawlers` listed a request the crawler detector flags is not recorded
in the fake either. `count()`,
`unique()`, `period()`, `collection()` and `countByInterval()` read from the fake. The `withViewsCount()` and
`orderByViews()` scopes need SQL and throw `UnsupportedInFake`; test those against the database.

The fake is backed by `Recording\Stores\ArrayStore`, which is also available as the `array` store driver for a
process that should keep views in memory without the assertions.

For tests and seeders that need rows in the `views` table, the `View` model ships a factory. `fromVisitor()`,
`inCollection()` and `viewedAt()` set the three columns a count reads; everything else is a plain Laravel factory.

```php
use CyrildeWit\EloquentViewable\Models\View;

View::factory()->for($post, 'viewable')->count(3)->create();
View::factory()->for($post, 'viewable')->fromVisitor('visitor_one')->inCollection('sidebar')->create();
View::factory()->for($post, 'viewable')->viewedAt(now()->subDays(2))->create();
```

A [custom `View` model](#using-your-own-view-eloquent-model) inherits the factory and gets instances of its own
class back from `factory()`.

## Optimizing

Storing every view as its own record is what makes detailed, time-based analytics possible, but it also means the
`views` table grows with traffic. For high-traffic applications, keep the following scalability considerations in mind:

- **Caching** counts (see below) to reduce load on the growing table.
- **Removing old records** you no longer need. The package does not prune records for you, so if you don't need a full
  history you can periodically delete rows from the `views` table yourself (for example with a scheduled command).
- **Table partitioning** at very large scale to keep queries fast.

### Database indexes

The `views` table migration creates two indexes: one on `viewable_type` and `viewable_id` (from `morphs()`), and a
composite one named `views_viewable_viewed_at_index` on `viewable_type`, `viewable_id` and `viewed_at`. The second one
lets `period()` counts and `countByInterval()` range-scan only the rows inside the period instead of every view of the
model.

If you ran the migration before that index existed, the
[upgrade guide](UPGRADING.md#add-an-index-on-viewable_type-viewable_id-and-viewed_at) has a migration you can copy into
your application to add it.

Two optional indexes for apps that need them, added in your own migration:

- `visitor` as a fourth column of that composite index (or `include (visitor)` on Postgres) makes `unique()` series
  index-only.
- `(viewable_type, viewed_at)` serves `views(Post::class)->countByInterval()` over a whole type, which the composite
  index above cannot narrow by date.

If you have enough storage available, you can add another index for the `visitor` column. Depending on the amount of
views, this may speed up unique view counts (`->unique()`) in some cases. The `visitor` column is a `string`
(`VARCHAR(255)`), so it can be indexed directly.

### Caching

Caching view counts can have a big impact on the performance of your application. You can read the documentation about
caching the view count [here](#caching-view-counts).

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
count. This can easily be achieved using a scheduled Laravel command.

There may be a faster way to do this, but such command can be like:

```php
$posts = Post::all();

foreach($posts as $post) {
    $post->unique_views_count = views($post)->unique()->count();
}
```

## Extending

If you want to extend or replace one of the core classes with your own implementations, you can override them:

- `CyrildeWit\EloquentViewable\Models\View`
- `CyrildeWit\EloquentViewable\Visitors\Visitor`
- `CyrildeWit\EloquentViewable\Crawlers\Detectors\CrawlerDetectAdapter`
- `CyrildeWit\EloquentViewable\Recording\Actions\RecordView`
- `CyrildeWit\EloquentViewable\Recording\Stores\DatabaseStore`
- `CyrildeWit\EloquentViewable\Recording\Stores\NullStore`
- `CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers`, `IgnoreDoNotTrack`, `IgnoreGlobalPrivacyControl`,
  `IgnoreIpAddresses` and `EnforceCooldown`
- `CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource`

> [!NOTE]
> Don't forget that all custom classes must implement their original interfaces.

### Custom information about visitor

The `Visitor` class reports what the request says about the current visitor. The guards turn those facts into a
decision, so a visitor never judges anything itself. It provides:

- a unique identifier (stored in a cookie named by `visitor.cookie.name`, for `visitor.cookie.lifetime` minutes)
- the IP address
- the user agent, including the device headers a proxy such as Opera Mini adds
- whether the Do Not Track header is set
- whether the Global Privacy Control signal is set

The default `Visitor` class gets its information from the request. Therefore, you may experience some issues when using
the `Views` builder via a RESTful API. To solve this, you will need to provide your own data about the visitor. Return
`null` for a user agent you do not have; a missing user agent is never treated as a crawler.

You can override the `Visitor` class globally or locally.

#### Create your own `Visitor` class

Create you own `Visitor` class in your Laravel application and implement the
`CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor` interface. Create the required methods by the interface.

Alternatively, you can extend the default `Visitor` class that comes with this package.

#### Globally

Simply bind your custom `Visitor` implementation to the `CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor`
contract.

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Visitors\Contracts\Visitor::class,
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

### Using your own `View` Eloquent model

Extend the shipped model and name your class in the config file. The package instantiates that class wherever it
reads or writes views, so the relation on your viewables, the counts and the record path all use it.

```php
// config/eloquent-viewable.php
'models' => [
    'view' => [
        'class' => \App\Models\View::class,
        // ...
    ],
],
```

```php
namespace App\Models;

use CyrildeWit\EloquentViewable\Models\View as BaseView;
use Illuminate\Database\Eloquent\SoftDeletes;

class View extends BaseView
{
    use SoftDeletes;
}
```

A class that does not extend `CyrildeWit\EloquentViewable\Models\View` throws `InvalidConfiguration` the first time it
is resolved. The `table_name` and `connection` keys stay the way to change those without a class. A `$table` or
`$connection` property on your subclass takes precedence over them at runtime. The published migration reads the two
config keys, so if you rename the table through the model, set `table_name` to match or edit the migration. The `Views`
builder has no replacement hook. It is `Macroable`, so add methods with `Views::macro()`, and
change how views are counted or stored by binding the actions and the store described below.

### Customizing how views are created

The `RecordView` action hands a `ViewRecord` to the store and dispatches the `ViewRecorded` event. Both the
synchronous and queued recording paths go through this action, so it is the single place to hook into if you want
to add attributes, skip the event, or write somewhere else entirely. The guards have already run by the time the
action is called; a view they refuse never reaches it.

Bind your custom implementation to the `\CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews` contract.

Change the following code snippet and place it in the `register` method in a service provider (for example
`AppServiceProvider`).

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews::class,
    \App\Actions\Views\RecordView::class
);
```

Your implementation receives the `ViewRecord` value object. It holds the viewable type and key, the visitor, the
collection and `viewed_at`, and returns nothing. The shipped action passes the record to the bound
`Recording\Contracts\ViewStore`, which is where the row is written and where `views($post)->destroy()` and a force
delete remove rows again. To change only where views are written, bind a store instead of replacing the action. See
[Choosing where views are stored](#choosing-where-views-are-stored).

```php
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordsViews;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

final class RecordView implements RecordsViews
{
    public function handle(ViewRecord $record): void
    {
        // ...
    }
}
```

### Choosing where views are stored

The `recording.store.driver` config key names the store that receives every recorded view. Two drivers ship:

- `database` writes a row to the views table. This is the default.
- `null` discards every view. Use it in an environment that should not record anything, or in a test suite that
  records views but never reads them back.

```php
'recording' => [
    'store' => [
        'driver' => 'database',
    ],
],
```

Counts always read from the views table, whichever driver is set. A store that writes somewhere else is a buffer in
front of that table and has to land its records there before they count.

To add a driver, implement `Recording\Contracts\ViewStore` and register it with the `StoreManager` in the `register`
method of a service provider. Then name it in the config.

```php
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;
use Illuminate\Contracts\Foundation\Application;

$this->app->make(StoreManager::class)->extend('clickhouse', fn (Application $app): ViewStore => new ClickHouseStore(
    $app->make(ClickHouseClient::class),
));
```

`store()` receives one `ViewRecord`. `storeMany()` receives an iterable of them and is how a buffer lands a batch
in as few writes as the store allows; the shipped database store issues one insert statement for the whole batch.
`forget()` receives a viewable and removes every view of it, in every collection. A viewable without a key stands for
every viewable of its type. `ViewRecord::toPayload()` flattens a record to scalars for a stream entry or a JSON body,
and `ViewRecord::fromPayload()` rebuilds it.

Stores write through the query builder, so `Models\View` model events are not fired when a view is stored. Listen for
`Recording\Events\ViewRecorded` instead. That event is dispatched once the store has accepted the record. With the
database store the row exists at that moment; a store that buffers writes lands it later. A listener reads what it
needs from `$event->record` and does not query the views table for the row.

```php
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Data\ViewRecord;

final class ClickHouseStore implements ViewStore
{
    public function store(ViewRecord $record): void
    {
        $this->storeMany([$record]);
    }

    public function storeMany(iterable $records): void
    {
        // $record->toPayload() for each record, in one request
    }

    public function forget(Viewable $viewable): void
    {
        // $viewable->getMorphClass(), $viewable->getKey()
    }
}
```

If the store does not need a name, binding the contract directly is the smaller change:

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore::class,
    \App\Views\ClickHouseStore::class
);
```

### Adding a recording guard

A guard decides whether a call to `record()` becomes a view. The `recording.guards` config key lists them in order,
and the first one that refuses drops the view. The list is the only switch: a guard runs when it is listed and not
otherwise. The package ships five. `IgnoreCrawlers`, `IgnoreIpAddresses` and `EnforceCooldown` are listed out of the
box; `cooldown()` does nothing without the last. The two privacy guards are commented out in the published config,
ready to switch on:

| Guard                        | Refuses                                         | Reads                            |
|------------------------------|-------------------------------------------------|----------------------------------|
| `EnforceCooldown`            | a second view inside the cooldown asked for     | the session                      |
| `IgnoreCrawlers`             | crawlers, judged by the bound `CrawlerDetector` | the visitor's user agent         |
| `IgnoreIpAddresses`          | listed IP addresses                             | `recording.ignored_ip_addresses` |
| `IgnoreDoNotTrack`           | visitors sending `DNT: 1`                       | the visitor                      |
| `IgnoreGlobalPrivacyControl` | visitors sending `Sec-GPC: 1`                   | the visitor                      |

The order only decides which guard is asked first. A cooldown starts once every guard has allowed the view, so a view
another guard drops never starts one, wherever `EnforceCooldown` is listed.

To add a guard, implement `Recording\Contracts\RecordingGuard` and add the class to the list. The guard receives a
`ViewAttempt` with the viewable, the visitor, the collection and the cooldown the call asked for. Guards are resolved
from the container, so constructor injection works.

```php
// config/eloquent-viewable.php
'recording' => [
    'guards' => [
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreCrawlers::class,
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreDoNotTrack::class,
        \CyrildeWit\EloquentViewable\Recording\Guards\IgnoreIpAddresses::class,
        \App\Views\Guards\IgnoreAuthors::class,
        \CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown::class,
    ],
],
```

```php
namespace App\Views\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use Illuminate\Contracts\Auth\Guard;

final readonly class IgnoreAuthors implements RecordingGuard
{
    public function __construct(private Guard $auth) {}

    public function allows(ViewAttempt $attempt): bool
    {
        return $attempt->viewable->author_id !== $this->auth->id();
    }
}
```

A class in the list that does not implement `RecordingGuard` throws `InvalidConfiguration` on the first `record()`.

A guard that keeps state about the views it lets through, as the cooldown does, also implements
`Recording\Contracts\RemembersRecordedViews`. Keep `allows()` free of side effects and write the state in
`remember()`, which runs once every guard has allowed the view and it has been stored or queued.

### Customizing how views are counted

Every number the package reports comes from one `Querying\Contracts\ViewSource`: `count()`, `countByInterval()`,
and the `withViewsCount()` and `orderByViews()` scopes. The `querying.source.driver` config key names it, and the
shipped `database` driver reads the views table.

```php
'querying' => [
    'source' => [
        'driver' => 'database',
    ],
],
```

To read from somewhere else, for example a rollup table, implement the contract and register a driver with the
`SourceManager` in the `register` method of a service provider.

The driver name is part of the `remember()` cache key, so switching `querying.source.driver` starts fresh cache
entries instead of serving counts the old source produced. A custom source with settings of its own, such as the name
of the rollup table, is identified by its driver name only; change `querying.cache.key` when those settings change.

```php
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Sources\SourceManager;
use Illuminate\Contracts\Foundation\Application;

$this->app->make(SourceManager::class)->extend('aggregate', fn (Application $app): ViewSource => new AggregateSource(
    $app->make(ViewAggregate::class),
));
```

The contract has three methods. `count()` returns a total. `countByInterval()` returns sparse counts keyed by the
bucket start formatted as `Y-m-d H:i:s`; buckets without views are left out, and the package fills them in.
`countSubquery()` returns a query selecting one integer, the count for the row of an outer query over the viewable's
table, which the scopes add as a subselect. It has to correlate on the viewable's qualified key. A viewable without a
key stands for every viewable of its type.

```php
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Query\Builder;

final class AggregateSource implements ViewSource
{
    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        // ...
    }

    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        // return ['2026-09-01 00:00:00' => 14, '2026-09-03 00:00:00' => 2];
    }

    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        // return DB::table('view_aggregates')
        //     ->whereColumn('view_aggregates.viewable_id', $viewable->getQualifiedKeyName())
        //     ->where('view_aggregates.viewable_type', $viewable->getMorphClass())
        //     ->selectRaw('coalesce(sum(views), 0)');
    }
}
```

If the source does not need a name, binding the contract directly is the smaller change:

```php
$this->app->bind(
    \CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource::class,
    \App\Views\AggregateSource::class
);
```

### Adding a bucket grammar for another database driver

The SQL that truncates `viewed_at` to a bucket differs per database, so the package ships a grammar for SQLite,
MySQL, MariaDB and Postgres. For another driver, implement `BucketGrammar` and register it in a service provider.
The column comes in already quoted, and the expression must yield the bucket start as `YYYY-MM-DD HH:MM:SS` with
weeks starting on Monday.

```php
use CyrildeWit\EloquentViewable\Querying\Grammars\GrammarRegistry;

$this->app->afterResolving(GrammarRegistry::class, function (GrammarRegistry $grammars): void {
    $grammars->register('sqlsrv', \App\Grammars\SqlServerBucketGrammar::class);
});
```

### Using a custom crawler detector

The `IgnoreCrawlers` guard hands the visitor's user agent to the bound `CrawlerDetector`, which answers whether it
belongs to a crawler. The shipped detector wraps [CrawlerDetect](https://github.com/JayBizzle/Crawler-Detect). A
detector is a function of the user agent string and holds no request state, so one instance serves the whole
process. A `null` or empty user agent is never a crawler.

```php
namespace App\Services\Views;

use CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector;

final class ListedCrawlerDetector implements CrawlerDetector
{
    public function isCrawler(?string $userAgent): bool
    {
        return $userAgent !== null && preg_match('/bot|crawler|spider/i', $userAgent) === 1;
    }
}
```

Bind it to the contract in the `register` method of a service provider (for example `AppServiceProvider`):

```php
$this->app->singleton(
    \CyrildeWit\EloquentViewable\Crawlers\Contracts\CrawlerDetector::class,
    \App\Services\Views\ListedCrawlerDetector::class
);
```

### Adding macros to the `Views` class

```php
use CyrildeWit\EloquentViewable\Views;

Views::macro('countAndRemember', function () {
    return $this->remember()->count();
});

Views::macro('countByDay', function () {
    return $this->countByInterval(Granularity::Day);
});
```

Now you're able to use these shorthands like this:

```php
views($post)->countAndRemember();
views($post)->period(Period::pastDays(30))->countByDay();

Views::forViewable($post)->countAndRemember();
```

## Upgrading

Please see [UPGRADING](UPGRADING.md) for detailed upgrade guide.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- **Cyril de Wit** - _Author_ - [cyrildewit](https://github.com/cyrildewit)

See also the list of [contributors](https://github.com/cyrildewit/eloquent-viewable/graphs/contributors) who
participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
