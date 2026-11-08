---
title: "Recommended for you"
description: "A \"Recommended for you\" row on a home page or below an article."
sidebar:
  order: 6
---

A "Recommended for you" row on a home page or below an article. `recommended()` takes the models one viewer viewed
recently, finds what the visitors of each also viewed, and adds those up, so the models that follow from more of their
recent views, and from the more recent ones, rank higher. What the viewer already viewed is left out:

```php
$posts = $user->recommended(Post::class, limit: 10);
```

The user model needs the [`HasViewHistory`](/eloquent-viewable/viewers/who-viewed-what/#the-viewer-side) trait, and views need to be
[linked to the signed-in user](/eloquent-viewable/viewers/who-viewed-what/#recording-the-viewer).

Every recommendation says which of the viewer's recent views it came from, so you can show the reason:

```blade
@foreach ($posts as $recommendation)
    <a href="{{ route('posts.show', $recommendation->viewable) }}">{{ $recommendation->viewable->title }}</a>
    <small>Because you read {{ $recommendation->because->first()->title }}</small>
@endforeach
```

```php
foreach ($user->recommended(Post::class) as $recommendation) {
    $recommendation->rank;      // 1, 2, 3, ...
    $recommendation->score;     // only comparable within this one list
    $recommendation->viewable;  // the recommended model
    $recommendation->because;   // up to 3 models the viewer viewed, the strongest reason first
}
```

## How the ranking is made

Say Alice read a post about Laravel queues today and a post about Docker last week. Of the readers of the queues post,
four went on to read the Redis guide. Of the readers of the Docker post, five went on to read the Kubernetes intro.

- **Recent views count more.** A view from one `half_life` ago, 7 days by default, counts half as much as a view from
  today. The Redis guide comes from today's read, so it ranks first even though fewer visitors share it.
- **Popular content does not win by default.** The number of visitors in common is divided by how many visitors each
  side has, so a page nearly everyone views does not top every list. Set `similarity` to `count` to rank by the
  visitors in common alone, as `alsoViewed()` does.
- **Only the most recent views are read.** The 20 models the viewer viewed most recently, and of each the 500 most
  recent visitors. Change them with `max_seeds` and `max_visitors`.

These options live under `querying.recommendations`.

## For guests, and in a query

Without `viewedBy()`, `recommended()` recommends to the current visitor, with the cookie or fingerprint of your
[visitor identity](/eloquent-viewable/viewers/who-viewed-what/#counting-one-account-as-one-visitor):

```php
views(Post::class)->recommended(10);                     // the current visitor, only posts
views(Post::class)->viewedBy($user)->recommended(10);    // the same as $user->recommended(Post::class, 10)
Views::viewedBy($user)->recommended(10);                 // any model type
```

`recommendedFor()` narrows a query to the models recommended to a viewer or a visitor id, ordered by score with the
score selected as `recommendation_score`. Your own constraints and pagination apply to every recommended model, not to
the first few:

```php
Post::query()
    ->where('published', true)
    ->recommendedFor($user)
    ->paginate();
```

## Narrowing it down

```php
$user->recommended(Post::class, period: Period::pastDays(30));     // only recent views, on every side
$user->recommended(Post::class, collection: 'articles');           // only one collection
$user->recommended(Post::class, includeSeen: true);                // keep what the viewer viewed before
views(Post::class)->viewedBy($user)->remember(now()->addHour())->recommended();  // cached
```

`includeSeen` suits content that is viewed again, such as products or music. A model the viewer viewed is never
recommended because of itself.

## Good to know

- **It shares the privacy floor of `alsoViewed()`.** A model needs `querying.also_viewed.minimum_visitors` visitors in
  common with one of the viewer's views, 3 by default. The viewer is never one of them, on any browser they were
  signed in on.
- **An empty list is a valid answer.** A new viewer, or a site with little traffic, gets nothing. Fall back to
  something else yourself, so every recommendation you show has a reason:

  ```php
  $posts = $user->recommended(Post::class);

  if ($posts->isEmpty()) {
      $posts = views(Post::class)->trending(10);
  }
  ```

- **It is built from the viewer's own history.** That makes it personal data: erasing a person's views with
  [`forgetViewHistory()`](/eloquent-viewable/viewers/erasing-views/) also ends their recommendations, and so does anonymising old
  views with `retention.anonymise`.
- **A cached list is forgotten by `flushCache()` only**, or when the moment `remember()` names passes. Keep that moment
  short, and add [a stale window](/eloquent-viewable/counting/caching/#serving-a-stale-answer-while-it-refreshes) so a page never waits for the next one:
  `remember(10, stale: 60)`.

## On large tables

`recommended()` runs the work of `alsoViewed()` for every model the viewer viewed recently, so its cost grows with how
many views those models have. Three things keep it fast:

- **Give it a short period.** Over all time, one call reads every view of every popular model the viewer opened. Over
  the last 30 days it reads only those, which on a million views is the difference between seconds and a tenth of a
  second. Recent views are usually the better signal anyway:

  ```php
  $user->recommended(Post::class, period: Period::pastDays(30));
  ```

- **Add the visitor index** from [Database indexes](/eloquent-viewable/scaling/indexes/). The viewer's own history is read through the
  `(viewer_type, viewer_id)` index the migration adds.
- **Turn on [the pairs table](/eloquent-viewable/scaling/pairs-table/)** for heavy traffic.

To recommend over all time, compute the list outside the request, in a queued job or a scheduled command, and keep it
with `remember()` or in a table of your own, so a page only reads the result.
