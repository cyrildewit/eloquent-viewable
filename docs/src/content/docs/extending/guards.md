---
title: "Recording guards"
description: "A recording guard decides whether a call to record() becomes a view. Add your own to skip staff accounts, internal traffic or anything else."
sidebar:
  order: 5
---

A guard decides whether a call to `record()` becomes a view. `recording.guards` lists them, and the first that refuses
drops the view:

| Guard                        | Refuses                                         | On by default |
|------------------------------|-------------------------------------------------|---------------|
| `IgnoreCrawlers`             | crawlers, judged by the bound `CrawlerDetector` | yes           |
| `IgnoreMissingUserAgent`     | requests without a user agent                   | yes           |
| `IgnoreIpAddresses`          | `recording.ignored_ip_addresses`                | yes           |
| `IgnoreHeadRequests`         | `HEAD` requests                                 | yes           |
| `IgnorePrefetch`             | pages the browser prefetches or prerenders      | yes           |
| `IgnoreOptedOutViewers`      | viewers whose `tracksViews()` returns `false`   | yes           |
| `IgnoreBursts`               | a visitor opening many models within seconds    | yes           |
| `EnforceCooldown`            | a second view inside the cooldown               | yes           |
| `ThrottleVisitors`           | views over `recording.throttle.max_per_minute`  | no            |
| `IgnoreDoNotTrack`           | visitors sending `DNT: 1`                       | no            |
| `IgnoreGlobalPrivacyControl` | visitors sending `Sec-GPC: 1`                   | no            |

Browsers always send a user agent, so a request without one is a script or a health check. Laravel answers a `HEAD`
request with your `GET` route, so without `IgnoreHeadRequests` every uptime monitor and link checker would count as a
view.

`recording.ignored_ip_addresses` takes single addresses and ranges, so `10.0.0.0/8` leaves out a whole office or VPN
network.

The cooldown only stops repeat views of the same model. A scraper that opens 5,000 different pages still records 5,000
views and pushes them up the [rankings](/eloquent-viewable/rankings/most-viewed/). `ThrottleVisitors` caps how many views one
visitor records per minute across every model, 60 by default. The counts are kept in the cache, so use a store that
every server shares.

`IgnoreBursts` catches the scrapers that pass for a browser. A visitor that opens more than 8 different models within 2
seconds is refused, and so is every view of theirs for the next 2 minutes. People do not read that fast; a bot opening
ten posts within a second does. Opening the same model again does not count towards a burst, that is the cooldown's
job. Change the rule under `recording.bursts`:

```php
'bursts' => [
    'max' => 8,
    'seconds' => 2,
    'block_for' => 120,
    'by' => ['visitor', 'network'],
    'store' => null,
],
```

- **`by` decides what a burst is counted per.** `visitor` is the stored visitor id. `network` is a hash of the network
  (the first three parts of an IPv4 address, the first three groups of an IPv6 one) and the user agent, the hash the
  `fingerprint` identity uses. A bot that drops its cookie gets a new visitor id on every request, so only `network`
  catches it. The hash lives in the cache for a few seconds and is never stored with a view.
- **People behind one address share a network.** An office, school or mobile carrier with one browser version shares
  the `network` count, so nine people opening different pages within the same two seconds trip it together. Set `by`
  to `['visitor']` if your readers come from such a network.
- **The views before the limit are recorded.** The guard only refuses from the burst on, so a bot keeps its first 8
  views. [`views:purge-bots`](/eloquent-viewable/scaling/purging-bots/) deletes those afterwards.
- **`Recording\Events\BurstDetected`** is dispatched once when a block starts, with the attempt and what it was counted
  per. The refusals during the block only dispatch `ViewSkipped`.
- **The counts are kept in the cache** with atomic operations, so parallel requests from one bot cannot slip under the
  limit together. Use a store that every server shares.

To add one, implement `Recording\Contracts\RecordingGuard` and add the class to the list. Guards are resolved from the
container.

```php
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use Illuminate\Contracts\Auth\Guard;

final readonly class IgnoreAuthors implements RecordingGuard
{
    public function __construct(private Guard $auth) {}

    public function allows(ViewAttempt $attempt): bool
    {
        return $attempt->viewable->author_id !== $this->auth->id();
    }
}
```

A guard that keeps state about the views it lets through, as the cooldown does, also implements
`Recording\Contracts\RemembersRecordedViews`, whose `remember()` runs once the view is stored or queued.
