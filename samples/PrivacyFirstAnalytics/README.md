# Privacy-first analytics

A documentation site wants to know which pages are read, and how many people read them, without a cookie banner. It
sets no cookies, stores no IP addresses or user agents, and honours the privacy signals browsers send. The pages are
served from a CDN cache, so the application never sees the page request itself; the page reports the view with a
beacon.

## The pieces

| File                                                                           | Role                                                        |
|--------------------------------------------------------------------------------|-------------------------------------------------------------|
| [`DocPage.php`](DocPage.php)                                                   | The viewable model                                          |
| [`RecordPageView.php`](RecordPageView.php)                                     | The beacon endpoint, which says why a view was not recorded |
| [`SkippedViews.php`](SkippedViews.php)                                         | A `ViewSkipped` listener that tallies the refusals          |
| [`create_doc_pages_table.php`](database/migrations/create_doc_pages_table.php) | The `doc_pages` table                                       |
| [`PrivacyFirstAnalyticsTest.php`](PrivacyFirstAnalyticsTest.php)               | The behaviour below, as tests, against `Views::fake()`      |

Identify visitors without a cookie, keep cooldowns out of the session, and list the guards in
`config/eloquent-viewable.php`:

```php
use CyrildeWit\EloquentViewable\Recording\Guards;

'recording' => [
    'guards' => [
        Guards\IgnoreCrawlers::class,
        Guards\IgnoreDoNotTrack::class,
        Guards\IgnoreGlobalPrivacyControl::class,
        Guards\IgnoreIpAddresses::class,
        Guards\EnforceCooldown::class,
    ],
    'ignored_ip_addresses' => ['10.20.0.0/16', '2001:db8:20::/48'],
],

'visitor' => [
    'identity' => 'fingerprint',
    'fingerprint' => [
        'store' => 'redis',
    ],
],

'cooldown' => [
    'store' => 'cache',
    'cache' => [
        'store' => 'redis',
    ],
],
```

Register the endpoint in `routes/api.php` and the listener, for example in `AppServiceProvider::boot()`:

```php
Route::post('/docs/{page}/views', RecordPageView::class);

Event::listen(ViewSkipped::class, SkippedViews::class);
```

Send the beacon from the page:

```html
<script>
    navigator.sendBeacon('/api/docs/{{ $page->id }}/views', JSON.stringify({ referrer: document.referrer }));
</script>
```

## Decisions

**A fingerprint instead of a cookie.** With `visitor.identity` set to `fingerprint`, the visitor id is a keyed hash of
the truncated IP address and the user agent, under a salt that is replaced at midnight. Neither the address nor the
user agent is stored, and once the salt is gone yesterday's hashes cannot be traced back or matched to today's. The
price is precision: a reader who comes back tomorrow is a new visitor, and two readers behind the same office router
with the same browser are one. Totals are unaffected; only `unique()` counts come out lower. Behind a load balancer or
CDN, configure trusted proxies, or every reader hashes the address of the proxy.

**Count each reader once a day.** The cooldown runs until midnight, when the fingerprint changes anyway, so a reader
who opens the installation page five times today is one view. That makes the plain `count()` of a period the sum of
the daily readers, which is the number the docs team wants, and the `cache` cooldown store keeps it working on an API
route without a session. The fingerprint store and the cooldown store must be shared by every server; the `array`
store does not work.

**Record from a beacon.** The page is cached at the edge, so a controller that renders it would only run on a cache
miss. The beacon reaches the application on every view. It is a `POST`, so the `views` middleware, which records only
successful `GET` requests, does not fit, and the endpoint calls `views()` itself. A crawler that does not run
JavaScript never sends the beacon; one that does is still dropped by `IgnoreCrawlers`.

**Honour the signals.** `IgnoreDoNotTrack` and `IgnoreGlobalPrivacyControl` are off by default and listed here, so a
reader whose browser sends `DNT: 1` or `Sec-GPC: 1` is not counted at all, not even as an anonymous hash.

**Leave out the staff network.** The writers proofreading the docs come from a /16 office network and a VPN range.
`recording.ignored_ip_addresses` takes CIDR ranges, so listing both ranges is enough.

**Store a word, not the referrer.** A referrer can hold a search query or the address of a private page. The endpoint
reduces it to `direct`, `internal`, `search` or `external` and stores only that word as the view's
[context](../../README.md#storing-context-with-a-view). It is enough for "how many readers find us through search" and
says nothing about the reader.

**Say why a view was not counted.** `attempt()` returns which guard refused the view, and the endpoint puts its name in
the response. `sendBeacon()` ignores the response, but a writer wondering why their own visits never show up can read
it in the network tab. The same refusals reach `SkippedViews` through the `ViewSkipped` event, which tallies them per
guard and per day, so the team can tell a page nobody reads from a page whose readers opt out.

**Test against the fake.** Nothing here counts with a scope, so the tests swap the store for `Views::fake()` and assert
on the recorded views directly, including their visitor and context, without a `views` table. The guards still run
against the fake.

## Where to take it next

- Show the readers per source with `$page->views()->where('context->source', 'search')->count()`, and add an
  indexed generated column for `source` once the views table is large.
- Show the tally from `SkippedViews` next to the counts on an internal stats page, so a drop in views after a browser
  turns on Global Privacy Control by default is not mistaken for a drop in interest.
- Buffer the beacons in Redis with the `redis` store, as in the [breaking news](../BreakingNews) sample, when a release
  announcement sends a spike of readers to the docs.
