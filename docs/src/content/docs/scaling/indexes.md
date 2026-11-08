---
title: "Database indexes"
description: "The indexes the migrations add, and the optional ones views:doctor recommends as the table grows."
sidebar:
  order: 2
---

The migration indexes `(viewable_type, viewable_id)` and `(viewable_type, viewable_id, viewed_at)`, so period counts and
series only read the rows inside the period, and `viewed_at`, so retention and rollups scan by date. If you installed
before the last two existed, the [upgrade guide](/eloquent-viewable/project/upgrading/#4-add-the-new-columns-indexes-and-tables) has a migration for
them.

Three optional indexes, added in a migration of your own. [`views:doctor`](/eloquent-viewable/scaling/checking-your-setup/) recommends the ones
your config relies on, and all three once the table passes a million rows:

- `visitor` as a fourth column of that composite index, or `include (visitor)` on Postgres, speeds up `unique()` counts,
  `returning()` and `countByFrequency()`.
- `(viewable_type, viewed_at)` speeds up counts over a whole type within a period, such as
  `views(Post::class)->countByInterval()`, and `rising()`, `anomalies()` and `views:detect-spikes`, which count every
  window of a type.
- `(visitor, viewed_at, viewable_type, viewable_id)` lets `alsoViewed()`, `recommended()` and `views:pairs` find the
  views of each visitor they pair without scanning the table.

On MySQL and MariaDB, the two indexes with `visitor` need it to be a string column. A table created before version 8
can still have a `text` one, and the [upgrade guide](/eloquent-viewable/project/upgrading/#4-add-the-new-columns-indexes-and-tables) shows how to
change it.
