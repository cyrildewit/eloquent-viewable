---
title: "Recording without a cookie"
description: "Count unique visitors without a cookie, and without a cookie banner for it, using a visitor fingerprint that rotates every day."
sidebar:
  order: 3
---

By default each guest gets a cookie with a random id, which `unique()` counts and cooldowns are keyed on. Set
`visitor.identity` to `fingerprint` to identify guests without one:

```php
'visitor' => [
    'identity' => 'fingerprint',
    'fingerprint' => [
        'store' => 'redis', // null uses the default cache store
    ],
],
```

The visitor id becomes an HMAC of the truncated IP address (/24 for IPv4, /48 for IPv6) and the user agent, keyed with a
salt that rotates at midnight. Neither the IP address nor the user agent is stored, and once the salt is gone a hash
cannot be traced back. Signed-in users are identified by their account instead, as with the
[`viewer` identity](/eloquent-viewable/viewers/who-viewed-what/#counting-one-account-as-one-visitor).

Set `visitor.fingerprint.rotation` to `week` or `month` to keep one salt longer. Weeks start on Monday:

```php
'fingerprint' => [
    'rotation' => 'month', // day, the default, week or month
],
```

A longer window keeps `unique()` exact over that window, but it also keeps one person linkable across every view they
make in it, for up to a month. Pick the shortest window your reports need, and say how long it is in your privacy
notice.

The trade-offs compared to the cookie:

- **`unique()` counts visitors per rotation window.** With the default `day`, the same guest on Monday and Tuesday
  counts twice over a week.
- **Cooldowns end when the salt rotates** at the latest.
- **Visitors sharing a network and browser count as one**, so unique counts come out lower. Totals are unaffected.
- **Configure trusted proxies** behind a load balancer or CDN, or every visitor hashes the same address.
- **Every server needs the same cache store** for the salt. The `array` store does not work.
- **Use the `cache` cooldown store**, because the `session` store still sets the session cookie.

Whether you need consent depends on your jurisdiction and the rest of your application, not only on this package.
