---
title: "Who is looking right now"
description: "Show how many people are looking at a model right now, with a live counter on cached pages."
sidebar:
  order: 7
---

To show "12 people are looking at this right now", you need the visitors of the last few minutes, not a count of
stored views. Presence keeps track of them in Redis, separately from the views, so it works whichever store records
your views. Turn it on:

```php
// config/eloquent-viewable.php
'presence' => [
    'enabled' => true,
    'driver' => 'redis',
],
```

From then on, every view that passes the guards marks its visitor as active for `presence.window` seconds, five minutes
by default:

```php
use CyrildeWit\EloquentViewable\Facades\Views;

views($product)->activeVisitors();                 // visitors of this product in the last 5 minutes
views($product)->live()->count();                  // the same
views($product)->collection('checkout')->live()->count();
views($product)->live()->within(60)->count();      // only the last minute
views(Product::class)->live()->count();            // visitors across all products
Views::live()->count();                            // visitors across the whole site

Views::forViewables($products)->live()->counts();  // [key => visitors] for a page of products

Views::live()->top(10);                            // what is being looked at right now
views(Product::class)->live()->top(10);            // the same, for products only
```

`top()` returns the same `Ranking` as [`Views::top()`](/eloquent-viewable/rankings/most-viewed/), with the number of active
visitors as each entry's `count`. It only considers the models seen most recently, 1000 by default
(`presence.max_candidates`).

## Staying active

Someone who reads a page for ten minutes should still count after five. Pass `live: true` to the
[beacon](/eloquent-viewable/recording/recording-views/#recording-from-the-browser):

```blade
@viewsBeacon($product, live: true)

<span data-views-live></span> people are looking at this right now
```

The script then tells the server every `presence.heartbeat` seconds (60 by default) that the page is still open. It
pauses while the tab is in the background, and when the page closes it asks the server to stop counting the visitor.
If the browser does not send that last request, the visitor drops out once the window has passed.

Set `presence.expose_count` to `true` to show the count on the page. Each heartbeat then returns the number of active
visitors, which the script writes into every `[data-views-live]` element and also sends as a `views:live` event:

```js
document.addEventListener('views:live', (event) => console.log(event.detail.active));
```

The heartbeat and leave URLs are signed like the beacon's, so they can be cached with the page. Build them yourself with
`app(\CyrildeWit\EloquentViewable\Http\Beacon::class)->presenceUrl($product)` and `leaveUrl($product)`, or do the
same from PHP:

```php
views($product)->heartbeat(); // keep the visitor active without recording a view
views($product)->leave();     // stop counting them on this product
```

## Cooldowns and guards

A cooldown or the throttle stops a visitor from being *counted* twice, but they are still *there*. So a view skipped by
either one still keeps its visitor active. `RecordResult::$present` tells you whether an attempt did.

The other guards do apply: crawlers, ignored IP addresses, bursts, prefetches, Do Not Track and Global Privacy Control
keep a visitor out of the live counts as well. A heartbeat passes the same guards, except the cooldown and the
throttle, and is never recorded as a view. To give a guard of your own the same treatment as the cooldown, implement
the `Recording\Contracts\LimitsRepeats` interface.

## Who is looking

With `presence.viewers` on, presence also remembers the signed-in [viewer](/eloquent-viewable/viewers/who-viewed-what/) of each view, for a
warning like "Alice and 2 others are editing this":

```php
views($document)->live()->viewers();     // signed-in users looking right now, most recent first
views($document)->live()->viewers(5);
```

It is off by default, because it stores who is looking instead of an anonymous id.

## Good to know

- **Exact or approximate.** By default (`presence.precision` set to `exact`) every active visitor is stored, so the
  count is exact. With `approximate`, Redis estimates the count with a
  [HyperLogLog](https://redis.io/docs/latest/develop/data-types/probabilistic/hyperloglogs/) per minute instead. Its
  memory does not grow with the number of visitors, but the count is an estimate, typically off by less than 1%. It
  works in whole minutes, so it can include visitors from up to a minute before the window, and `leave()` has no
  effect.
- **Redis.** Works with phpredis and Predis. Pick a connection with `presence.redis.connection`. All keys share one
  [hash tag](https://redis.io/docs/latest/operate/oss_and_stack/reference/cluster-spec/#hash-tags), so on a Redis
  Cluster they all live on the same node. Keys expire on their own once nobody has been seen for a while. If Redis
  rejects a write, the error is reported and the view is still recorded.
- **Privacy.** Presence stores a hash of the visitor id, and with `presence.viewers` on also the viewer's type and
  key. Nothing is kept for long: entries expire a few minutes after the window.
- **Cookies.** With the `cookie` [identity](/eloquent-viewable/recording/without-a-cookie/), a browser that blocks cookies gets a new
  visitor id on every heartbeat and is counted more than once. The `fingerprint` identity avoids this.
- **When it is off.** While `presence.enabled` is `false`, recording skips presence entirely and reading a live count
  throws `InvalidConfiguration`, so a forgotten setting does not look like an empty page.
