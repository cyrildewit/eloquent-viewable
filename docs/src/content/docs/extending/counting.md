---
title: "How views are counted"
description: "Every number comes from one Querying\\Contracts\\ViewSource, named by querying.source.driver."
sidebar:
  order: 7
---

Every number comes from one `Querying\Contracts\ViewSource`, named by `querying.source.driver`. The shipped `database`
source reads the `views` table. To read from somewhere else, such as a rollup table, implement the contract and register
it:

```php
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Sources\SourceManager;

$this->app->make(SourceManager::class)->extend('aggregate', fn ($app): ViewSource => new AggregateSource(
    $app->make(ViewAggregate::class),
));
```

The contract has one method per way of counting: `count()`, `countByInterval()`, `countByCollection()`, `countMany()`
and `top()`. Each method's docblock describes what it returns; the package fills in missing buckets and zeros and sorts
the results.

The scopes add SQL to a query over the viewable's table, so they need a source in the same database that also
implements `Querying\Contracts\SubquerySource`: `countSubquery()` for the counts and `viewsSubquery()` for the
`whereViewedBy()` checks. Any other source throws `Querying\Exceptions\UnsupportedBySource` from a scope. The `database`
source implements both, and a rollup can hand `viewsSubquery()` on to it so the existence checks keep reading the
`views` table.

`trending()` needs a source that also implements `Querying\Contracts\RanksTrending`, and the trending scopes one that
implements `Querying\Contracts\TrendingSubquerySource`. Without them, they throw `UnsupportedBySource`. Both receive a
`Decay` whose `steps()` already hold every weight, so a source only has to sort views into steps.

`returning()` and `countByFrequency()` need a source that also implements `Querying\Contracts\CountsVisitFrequency`,
whose `visitFrequency()` returns the number of visitors per number of days they viewed on. Without it, they throw
`UnsupportedBySource`.

`views:recount` recounts every model on every run through a source of your own, unless it also implements
`Querying\Contracts\TracksChangedViewables`: `keysViewedBetween()` returns the keys of the viewables of a type with
views in a window, counting whatever the source keeps beyond the `views` table, and `countsChangeWhenPruned()` says
whether a count changes once old views are deleted. With it, only the models whose counts can have changed are
recounted, as under the `database` and `rollup` sources.

`remember()` keeps the entries of two sources apart by the driver name. A source whose counts depend on settings of its
own, such as the name of the rollup table, implements `Querying\Contracts\IdentifiesSource` and returns them from
`cacheIdentity()`, so changing them starts fresh entries.
