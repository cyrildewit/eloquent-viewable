---
title: "Storing counts on your own table"
description: "Keep view counts in a column on your own table, so ordering and filtering by views read an index."
sidebar:
  order: 8
---

`remember()` caches counts, but `orderByViews()` and `whereViewsCount()` still count in SQL on every query. For large
lists, keep the count in a column of your own, default `0`, and sort on that. List the column under
`querying.counters`, by name for the all-time count or with the `unique`, `period` and `collection` of its count:

```php
'querying' => [
    'counters' => [
        Post::class => [
            'views_count',
            'unique_views_count' => ['unique' => true],
            'views_last_week' => ['period' => '7d'],
        ],
    ],
],
```

A column can also count only the views of some [dimension](/eloquent-viewable/viewers/dimensions/) values, with `dimensions` mapping each
dimension to one value or a list of values:

```php
'google_views' => ['period' => '30d', 'dimensions' => ['source' => 'Google']],
```

`views:recount` writes the columns, trashed models included, and `views:maintain` runs it after rolling up and
pruning, so one scheduler line keeps them fresh. A column is as fresh as the last run. Name it apart from
`views_count` when you also use `withViewsCount()`, whose alias is the same.

A `unique` column leaves out the views anonymised by [retention](/eloquent-viewable/scaling/retention/), under every source. Their visitor ids
change from day to day, so counting them would count a returning visitor again on every day, or on every bucket of the
rollups. Once views are anonymised, the column counts the visitors since `retention.anonymise.after` rather than of all
time, and can drop as the views it counted are anonymised.

With the [retention migration](/eloquent-viewable/scaling/retention/) installed, a recount only touches the models whose counts can have changed
since the last one: models with new views, models with views that left the period of a column, and, for unique
columns, models with views that were anonymised. The rest of the table is left alone, which matters once it holds
millions of rows. The first run, a run after you change the columns or the source, and `views:recount --full`
recount every model. So do the run after `views:purge-bots` and every run after views were pruned under the
`database` source, because a deleted view no longer says whose it was. Use the [`rollup` source](/eloquent-viewable/scaling/rollups/) to avoid
the second. Without the retention migration every run recounts every model.

`views($post)->destroy()` recounts the post's columns right away.

The columns are written with a query builder `UPDATE`, so no model events fire. Every recount dispatches
`Querying\Counters\Events\CountersRecounted` instead, once per chunk, with the model class and the keys it recounted.
Listen to it to keep a copy of the columns elsewhere in sync, such as a search index:

```php
use CyrildeWit\EloquentViewable\Querying\Counters\Events\CountersRecounted;

Event::listen(function (CountersRecounted $event): void {
    $event->class::query()->whereKey($event->keys)->searchable();
});
```

The keys are every model the recount wrote, also those whose counts stayed the same.
