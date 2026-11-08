---
title: "Partitioning"
description: "Partitioned by viewed_at, a month of views goes with one DROP PARTITION instead of a delete per row."
sidebar:
  order: 7
---

Partitioned by `viewed_at`, a month of views goes with one `DROP PARTITION` instead of a delete per row. The package does
not create or drop partitions, but works on a partitioned table unchanged: keep rollups folding and `views:anonymise`
anonymising, and let dropping partitions take the place of `prune.after`.

The partition key must be part of the primary key, so create the table in a migration of your own instead of the
published one. On MySQL and MariaDB:

```php
DB::statement(<<<'SQL'
    CREATE TABLE views (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        viewable_type VARCHAR(255) NOT NULL,
        viewable_id BIGINT UNSIGNED NOT NULL,
        viewer_type VARCHAR(255) NULL,
        viewer_id BIGINT UNSIGNED NULL,
        visitor VARCHAR(255) NULL,
        collection VARCHAR(255) NULL,
        context JSON NULL,
        viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id, viewed_at),
        INDEX views_viewable_viewed_at_index (viewable_type, viewable_id, viewed_at),
        INDEX views_viewed_at_index (viewed_at)
    )
    PARTITION BY RANGE (UNIX_TIMESTAMP(viewed_at)) (
        PARTITION p2026_01 VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01 00:00:00')),
        PARTITION p_future VALUES LESS THAN MAXVALUE
    )
    SQL);
```

Drop a month only once every rollup has folded it, then record the drop with `views:prune --before`, so the `rollup`
source knows the `views` table no longer holds it:

```php
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\Watermarks;

Schedule::call(function (): void {
    $oldest = now()->subMonths(13)->startOfMonth();
    $end = $oldest->copy()->addMonth();

    if (app(Watermarks::class)->clamp($end)->lt($end)) {
        return;
    }

    DB::statement("ALTER TABLE views DROP PARTITION p{$oldest->format('Y_m')}");
    Artisan::call('views:prune', ['--before' => $end->toDateTimeString()]);
})->monthly()->onOneServer();
```

What to know:

- **Create partitions ahead.** On MySQL, split `p_future` with `REORGANIZE PARTITION` each month.
- **On Postgres**, create the table with `PRIMARY KEY (id, viewed_at)` and `PARTITION BY RANGE (viewed_at)`, a table per
  month with `CREATE TABLE views_2026_01 PARTITION OF views FOR VALUES FROM ('2026-01-01') TO ('2026-02-01')`, and drop
  one with `DETACH PARTITION` and `DROP TABLE`.
- **Leave `prune.after` unset**, so `views:maintain` does not delete rows from months you mean to drop whole.
- **Publish the retention and rollups migrations as usual.** Neither touches the `views` table.
