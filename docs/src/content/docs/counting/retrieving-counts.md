---
title: "Retrieving counts"
description: "Count all or unique views of a model, narrow them to a period, compare with the period before and group them for a chart."
sidebar:
  order: 1
---

```php
views($post)->count();
views($post)->unique()->count();
views(Post::class)->count();            // every post together, also views(new Post)
```

## Periods

Narrow a count to a period:

```php
use CyrildeWit\EloquentViewable\Support\Period;

views($post)->period(Period::create('2017-01-01', '2018-01-01'))->count();
```

A period includes its start and excludes its end, so `Period::create('2018-01-01', '2018-02-01')` is January.

```php
Period::create($start, $end);
Period::since($start);
Period::upto($end);                    // up to, but not including

Period::pastDays(7);                   // from midnight 7 days ago, also pastWeeks, pastMonths, pastYears
Period::subHours(6);                   // from now minus 6 hours, also subSeconds, subMinutes, subDays, ...
Period::pastDays(7, 'Australia/Sydney'); // from midnight in that timezone
```

`Period::parse()` reads the short forms a dashboard puts in a URL, and `Period` binds as a route parameter, responding
with a 404 to anything it cannot read:

```php
Period::parse('7d');                       // pastDays(7), likewise 3w, 6m, 1y
Period::parse('12h');                      // subHours(12), likewise 30min and 90s
Period::parse('2026-01-01..2026-02-01');   // create(), and '2026-01-01..' or '..2026-02-01' for open ends

Route::get('/posts/{post}/stats/{period}', fn (Post $post, Period $period) => views($post)->period($period)->count());

route('posts.stats', [$post, Period::pastDays(7)]); // /posts/1/stats/7d
```

`viewed_at` is stored in your application timezone. Keep `app.timezone` at `UTC` unless you have a reason not to.

## Compare with the previous period

`compare()` counts the period and the one right before it, of the same width:

```php
$trend = views($post)->period(Period::pastDays(7))->compare();

$trend->current;        // 340
$trend->previous;       // 290
$trend->delta;          // 50
$trend->percent;        // 17.2, or null when there were no views before
$trend->previousPeriod; // Period, for a "compared with 20–27 Aug" label
```

The result is `JsonSerializable`, so a controller can return it. A period without both bounds, such as
`Period::since()`, has no width and throws `InvalidPeriod`.

## Get view counts grouped by interval

`countByInterval()` returns the count per hour, day, week, month or year, with empty buckets filled in with zero:

```php
use CyrildeWit\EloquentViewable\Support\Granularity;

$series = views($post)
    ->period(Period::pastDays(30))
    ->countByInterval(Granularity::Day);

$series->labels();  // ['2026-09-01', '2026-09-02', ...]
$series->values();  // [14, 22, ...]
$series->total();   // the same number as count() over the period
$series->peak();    // the busiest Bucket, with start, end, label and count
```

The series is `JsonSerializable`, so it feeds a chart straight from a controller. Buckets follow the calendar, weeks
start on Monday, and `views($post)->period($bucket->period())->count()` gives the same number as the bucket.

Buckets follow your application timezone. Pass `timezone('Australia/Sydney')` to start each day at Sydney midnight;
daylight saving transitions are handled without timezone tables in the database. A call that would produce more than
`querying.max_intervals` buckets (10,000 by default) throws `InvalidInterval`.

Grouping happens in the database, with a grammar for SQLite, MySQL, MariaDB and Postgres. Other drivers need
[a grammar of their own](/eloquent-viewable/extending/bucket-grammars/).

## Get view counts per collection

```php
views($post)->countByCollection();
// ['' => 1200, 'sidebar' => 340, 'feed' => 88], most viewed first, '' for views without a collection
```

## Combining

Every option combines with every way of counting: `unique()`, `period()`, `collection()`, `viewedBy()`, `timezone()` and
`remember()` work with `count()`, `compare()`, `countByInterval()`, `countByCollection()`, `counts()` and `top()`.
