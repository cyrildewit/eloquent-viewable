---
title: Quick start
description: Record your first view, count it, chart it and rank it, in five steps.
sidebar:
  order: 4
---

From an empty install to a trending list in five steps. Each one links to the page that covers it in full.

## 1. Install

```bash
composer require cyrildewit/eloquent-viewable:^9
php artisan vendor:publish --provider="CyrildeWit\EloquentViewable\EloquentViewableServiceProvider" --tag="migrations"
php artisan migrate
```

See [Installation](/eloquent-viewable/getting-started/installation/) for publishing the config and for models keyed
by a UUID or ULID.

## 2. Make a model viewable

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class Post extends Model implements Viewable
{
    use InteractsWithViews;
}
```

## 3. Record a view

Add the `views` middleware to the route that shows the model. It records a view for every successful `GET`, and the
cooldown keeps a reader who refreshes the page from counting twice:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

Route::get('/posts/{post}', ShowPost::class)
    ->middleware(RecordViews::using('post', cooldown: 30));
```

Or call `views($post)->record()` in the controller. Crawlers, prefetches and `HEAD` requests are dropped either way.
See [Recording views](/eloquent-viewable/recording/recording-views/) and
[Cooldowns](/eloquent-viewable/recording/cooldowns/).

## 4. Count and chart

```php
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;

views($post)->count();                                      // every view
views($post)->unique()->period(Period::pastDays(7))->count(); // unique visitors this week

$trend = views($post)->period(Period::pastDays(7))->compare();
$trend->percent;                                            // 17.2, against the week before

$series = views($post)
    ->period(Period::pastDays(30))
    ->remember(60)
    ->countByInterval(Granularity::Day);

$series->labels();                                          // ['2026-09-01', '2026-09-02', ...]
$series->values();                                          // [14, 22, ...]
```

See [Retrieving counts](/eloquent-viewable/counting/retrieving-counts/) and
[Caching](/eloquent-viewable/counting/caching/).

## 5. Rank

```php
Post::orderByViews()->paginate();                           // most viewed first

foreach (views(Post::class)->trending(10) as $entry) {
    $entry->viewable;                                       // the post
    $entry->count;                                          // its views lately
}
```

See [Ordering and filtering](/eloquent-viewable/counting/ordering-and-filtering/) and
[Trending](/eloquent-viewable/rankings/trending/).

## Next

- Run [`php artisan views:doctor`](/eloquent-viewable/scaling/checking-your-setup/) to check the setup.
- Read [Testing](/eloquent-viewable/testing/) before you write the first test that records a view.
- Browse the [recipes](/eloquent-viewable/recipes/) for complete features built from these pieces.
