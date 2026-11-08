---
title: "Introduction"
description: "What Eloquent Viewable is, what it can do and how it grows with your traffic."
sidebar:
  order: 0
---

**Eloquent Viewable** is a flexible and minimalistic analytics package for Laravel that allows seamless tracking of
views for Eloquent models. Rather than incrementing a single counter, it stores each view as its own database record,
so you can analyze totals, unique visitors, and custom time periods entirely within your own application. Whether
you're running a blog, an e-commerce store, or a custom Laravel application, this package lets you log and analyze
views without relying on external analytics services.

## Quick Example

Once installed, you can track and retrieve views effortlessly:

```php
// Record a view
views($post)->record();

// Return total view count
views($post)->count();

// Return unique view count of the past 7 days, compared with the 7 days before
views($post)->unique()->period(Period::pastDays(7))->compare();

// Daily counts for a chart
views($post)->period(Period::pastDays(30))->countByInterval(Granularity::Day);
```

## Key Features

- Track **total** and **unique** views for any Eloquent model, from a controller, a route middleware or the browser
  when the page comes from a full-page cache
- Query views by custom periods, compare with the previous period and group them **per hour, day, week, month or year**
- Order and filter models by views, rank the **most viewed content** across every model, and find what's **trending
  right now**
- Show what **people who viewed this also viewed**, and **recommend** to each viewer what the visitors of the models they
  viewed recently went on to view, with the reason for every recommendation
- Know **who viewed what** by linking views to the signed-in user
- Show **who is looking right now**, "12 people are viewing this", with a live counter on cached pages
- Prevent duplicate views with a configurable **cooldown system**
- Count unique visitors **without a cookie**, with a daily rotating fingerprint
- Ignore views from **crawlers, blocked IPs, prefetches, and visitors who opt out** with Do Not Track, Global Privacy
  Control or a setting on their account
- Scale with **caching, queued recording or a Redis buffer**, and test with `Views::fake()`

## Start simple, scale when you need to

Out of the box every view is one insert during the request and every count is one query. That is fine for most
applications and needs nothing beyond the migrations. When traffic grows, switch on what you need, one config key at a
time. None of these changes how you query.

| Option                                                  | What you gain                                                    | What you need                                     |
|---------------------------------------------------------|------------------------------------------------------------------|---------------------------------------------------|
| Default                                                 | Views count immediately, nothing to set up                       | Nothing                                           |
| [`remember()`](/eloquent-viewable/counting/caching/)                    | Repeated counts, series and rankings served from the cache       | Any cache store                                   |
| [Queued recording](/eloquent-viewable/recording/queueing/)            | The insert moves out of the request                              | A queue worker                                    |
| [Redis buffer](/eloquent-viewable/scaling/redis-buffer/)               | One Redis write per request, one insert per thousand views       | Redis 7+ or Valkey, and a scheduled `views:flush` |
| [Optional indexes](/eloquent-viewable/scaling/indexes/)                   | Faster unique counts and counts over a whole model type          | A migration of your own                           |
| [Counts on your own table](/eloquent-viewable/scaling/counts-table/) | Fast sorting of large lists by views                       | A column and a scheduled `views:maintain`         |
| [Cache cooldowns](/eloquent-viewable/recording/cooldowns/)                  | Cooldowns on stateless API routes                                | Any shared cache store                            |
| [Fingerprint identity](/eloquent-viewable/recording/without-a-cookie/)     | Unique visitors without setting a cookie                         | A shared cache store, trusted proxies configured  |
| [Retention](/eloquent-viewable/scaling/retention/)                                 | Old views anonymised and deleted on a schedule                   | A scheduled `views:maintain`                      |
| [Rollups](/eloquent-viewable/scaling/rollups/)                                     | History and cheap all-time counts after views are deleted        | The `rollup` source                               |
