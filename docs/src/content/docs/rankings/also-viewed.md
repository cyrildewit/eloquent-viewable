---
title: "Also viewed"
description: "Show related content based on what your visitors actually do, such as a \"Readers also read\" box under an article or \"Customers also viewed\" on a product page."
sidebar:
  order: 5
---

Show related content based on what your visitors actually do, such as a "Readers also read" box under an article or
"Customers also viewed" on a product page. `alsoViewed()` looks at everyone who viewed a model and finds what else
those people viewed:

```php
$related = views($post)->alsoViewed(5);
```

```blade
@foreach ($related as $entry)
    <a href="{{ route('posts.show', $entry->viewable) }}">{{ $entry->viewable->title }}</a>
@endforeach
```

## How the ranking is made

Say three visitors read your post about Laravel queues:

| Visitor | Also viewed                |
|---------|----------------------------|
| Alice   | Redis guide, Horizon video |
| Bob     | Redis guide                |
| Carol   | Redis guide, Horizon video |

`views($queuesPost)->alsoViewed()` ranks the Redis guide first with a count of 3, because all three visitors viewed
it, and the Horizon video second with a count of 2. A visitor who viewed something many times still counts once, and
the post itself is never in the list.

Each entry has the model and that count:

```php
foreach (views($post)->alsoViewed(5) as $entry) {
    $entry->rank;      // 1, 2, 3, ...
    $entry->count;     // how many visitors viewed both
    $entry->viewable;  // a Post, a Video, ... whichever model it is
}
```

## Narrowing it down

```php
views($post)->alsoViewed(5, among: Post::class);           // only posts
views($post)->period(Period::pastDays(30))->alsoViewed(5);  // only recent views
views($post)->collection('sidebar')->alsoViewed(5);         // only one collection
views($post)->remember(now()->addHour())->alsoViewed(5);    // cached
```

The period and collection apply to both sides: the views of the post and the views of everything else.
`forgetCache()` on the post also forgets its cached ranking.

## Good to know

- **Something needs at least 3 visitors in common to show up.** This keeps the list from exposing what one or two
  people looked at, and keeps noise out. Change it with `querying.also_viewed.minimum_visitors`. On a fresh site with
  little traffic, the list stays empty until enough people have visited.
- **"The same visitor" follows your [visitor identity](/eloquent-viewable/viewers/who-viewed-what/#counting-one-account-as-one-visitor).** With the default cookie
  it is one browser, with `viewer` one account across devices. With `fingerprint`, and on views older than
  `retention.anonymise.after`, a visitor is only recognised within a single day.
- **Popular content shows up everywhere.** Your homepage or most-read article is viewed by almost everyone, so it
  tends to rank high next to any post. Use `among:` or filter the results if that gets in the way.
- **It counts visitors.** `unique()` changes nothing and `viewedBy()` is refused. To list what one user viewed, use
  [`whereViewedBy()`](/eloquent-viewable/viewers/who-viewed-what/#which-models-a-viewer-has-seen).

## On large tables

To find the other views, the query reads every view of every visitor of the post. Two things keep that fast:

- **The visitor index.** Add `(visitor, viewed_at, viewable_type, viewable_id)` from
  [Database indexes](/eloquent-viewable/scaling/indexes/). Without it, every call scans the whole `views` table.
- **The visitor cap.** Only the 1,000 most recent visitors of the post are read. Change it with
  `querying.also_viewed.max_visitors`, or set it to `null` to read every visitor.

For heavy traffic, turn on [the pairs table](/eloquent-viewable/scaling/pairs-table/), which a scheduled command fills, so a call reads a few
rows instead of every view of every visitor. The `rollup` source reads the pairs from the `views` table, so they only
cover the views it still holds.
