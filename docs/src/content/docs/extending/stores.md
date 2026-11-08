---
title: "Where views are stored"
description: "Pick where recorded views go with recording.store.driver: the views table, a Redis buffer, memory for tests, nowhere, or a store of your own."
sidebar:
  order: 4
---

`recording.store.driver` names the store for every recorded view:

- `database` writes a row to the `views` table. This is the default.
- `redis` buffers views in a Redis stream, see [Buffering views in Redis](/eloquent-viewable/scaling/redis-buffer/).
- `array` keeps views in memory for the process, see [Testing](/eloquent-viewable/testing/).
- `null` discards every view.

Counts read from the configured [view source](/eloquent-viewable/extending/counting/), and the shipped `database` and
`rollup` sources read the `views` table and the rollups built from it. A store that writes elsewhere is then a buffer
in front of it and implements `Recording\Contracts\BufferedViewStore`, so `views:flush` can drain it.

A store can also keep views somewhere else for good, such as MongoDB, when it is paired with a source that reads them
back from there. The `views()` and `viewed()` relations, `hasViewed()`, `lastViewedAt()`, erasure, retention and
rollups keep working on the `views` table, and the scopes need a source in the same database as the viewable.

To add a driver, implement `Recording\Contracts\ViewStore` and register it in a service provider:

```php
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\StoreManager;

$this->app->make(StoreManager::class)->extend('clickhouse', fn ($app): ViewStore => new ClickHouseStore(
    $app->make(ClickHouseClient::class),
));
```

A store implements `store()` for one record, `storeMany()` for a batch and `forget()` to remove every view of a
viewable. A buffer also implements `flush()`, and `land()`, which lands the buffered views a filter
keeps straight away, so [erasing one person's views](/eloquent-viewable/viewers/erasing-views/) reaches them. Stores bypass Eloquent, so listen for `Recording\Events\ViewRecorded` and not for `View` model events.
