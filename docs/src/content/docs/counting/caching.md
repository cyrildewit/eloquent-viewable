---
title: "Caching"
description: "Cache view counts, chart series and rankings with remember(), with an optional stale window, so busy pages don't query the views table on every request."
sidebar:
  order: 4
---

Add `remember()` with a lifetime to cache a count, a series or a ranking. An integer lifetime is a number of minutes.
Without a lifetime, `remember()` caches nothing, so `remember()` turns off the caching an earlier call asked for:

```php
views($post)->remember(60)->count();                            // for an hour
views($post)->remember(now()->addWeeks(2))->count();
views($post)->period(Period::pastDays(30))->remember(60)->countByInterval(Granularity::Day);
Views::period(Period::pastDays(7))->remember(10)->top(10);
```

Relative periods such as `Period::pastDays(30)` are cached too. Recording a view leaves a remembered count alone;
deleting views forgets it. To forget remembered counts yourself, for example after an import:

```php
views($post)->forgetCache();      // the post, its type and every ranking
views(Post::class)->forgetCache(); // every post
Views::flushCache();               // everything
```

This works on every cache store, including those without tags. For fresher counts, use a shorter lifetime.

## Serving a stale answer while it refreshes

Once a remembered answer expires, the next read counts it again and waits for the database. On a busy page with an
expensive read, such as a ranking or recommendations, pass `stale` to keep serving the old answer while it is counted
again, as Laravel's `Cache::flexible()` does:

```php
Views::period(Period::pastDays(7))->remember(10, stale: 60)->trending(10); // fresh for 10 minutes, kept for an hour
```

For ten minutes, reads come from the cache. After that, until the hour is up, a read returns the old answer at once and
counts it again once the response is sent. One process counts at a time, and the others keep serving the old answer.
An answer nobody read for the whole hour is counted again before the response, as without `stale`.

- **`stale` is measured from now, like the lifetime**, so it must end after it. It takes minutes or a
  `DateTimeInterface`.
- **The refresh runs after the response**, through Laravel's `defer()`. In a queued job or an Artisan command it runs
  when the job or command ends, also when it failed. On the `sync` queue it runs with the request that dispatched the
  job. A refresh that throws is reported, and the old answer stays until the hour is up.
- **A forgotten answer is never served stale.** `forgetCache()`, `flushCache()`, deleting views, erasure, anonymising
  and pruning make the next read count again before it answers.
- **A fold or a rebuild of the pairs marks answers stale instead.** They only add views, as recording does, so with
  `stale` an hourly `views:maintain` no longer makes the first read after it wait. Without `stale`, that read counts
  again before it answers.
- **`counts()` refreshes its stale models together**, in one query.
