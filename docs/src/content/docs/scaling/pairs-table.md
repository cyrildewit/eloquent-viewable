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

Each run rewrites the table in one transaction from the views of the last `period`, 90 days by default. Of each model it
keeps the `max_pairs` models it shares the most visitors with, 100 by default, and only pairs that reach
`querying.also_viewed.minimum_visitors`. Run it again after you change either.

Good to know:

- **The table only answers calls over all time and every collection.** A call with a `period()` or `collection()`
  reads the `views` table, as before.
- **Between two runs, new views are not paired.** A post published this morning has no pairs until the next run.
- **A viewer is not left out of the pairs in the table**, because it does not know who they were. A model the viewer
  viewed, on any browser, therefore counts one visitor fewer, so the privacy floor holds.
