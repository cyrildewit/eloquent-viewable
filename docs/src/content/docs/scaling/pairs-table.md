---
title: "The pairs table"
description: "Precompute the pairs alsoViewed() and recommended() read, so they stay fast on large tables."
sidebar:
  order: 5
---

`alsoViewed()` and `recommended()` read every view of every visitor they pair, on every call. With heavy traffic,
let a scheduled command count the pairs once into a table of their own, so a call reads a few rows.

Turn it on with `querying.pairs.enabled` and schedule `views:pairs`, daily or hourly:

```php
Schedule::command('views:pairs')->daily();
```

Each run pairs the views of every one of `periods`, `['90d']` by default, and rewrites the pairs of each period in one
transaction. Of each model it keeps the `max_pairs` models it shares the most visitors with, 100 by default, and only
pairs that reach `querying.also_viewed.minimum_visitors`. Run it again after you change any of them.

To serve a call over a recent period from the table too, add that period:

```php
'pairs' => [
    'enabled' => true,
    'periods' => ['30d', '90d'],
],
```

`views:pairs --period=30d` rewrites one period only, so a short period can run more often than a long one:

```php
Schedule::command('views:pairs --period=30d')->hourly();
Schedule::command('views:pairs --period=90d')->daily();
```

Good to know:

- **The table answers a call over one of its periods, or over all time.** `Period::pastDays(30)` and
  `Period::subDays(30)` read the pairs of `30d`, and a call without a period reads those of the longest period. Any
  other period, a `collection()` or a filter reads the `views` table, as before.
- **Every period is another pass over the views.** Pair only the periods you call with, and add the
  `(visitor, viewed_at, viewable_type, viewable_id)` index from [Database indexes](/eloquent-viewable/scaling/indexes/)
  first: without it, a run scans the `views` table for every chunk of models.
- **Between two runs, new views are not paired.** A post published this morning has no pairs until the next run.
- **A viewer is not left out of the pairs in the table**, because it does not know who they were. A model the viewer
  viewed, on any browser, therefore counts one visitor fewer, so the privacy floor holds.
