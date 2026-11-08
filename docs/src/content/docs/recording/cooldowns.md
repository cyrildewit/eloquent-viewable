---
title: "Cooldowns"
description: "A cooldown ignores repeated views of the same model by the same visitor for a number of minutes, or until a given time."
sidebar:
  order: 2
---

A cooldown ignores repeated views of the same model by the same visitor for a number of minutes, or until a given time:

```php
views($post)->cooldown(30)->record();
views($post)->cooldown(now()->addHours(3))->record();
```

While a cooldown runs, `record()` returns `false`. Two requests that arrive at the same time can still both be recorded.

Cooldowns are kept in the session by default, so they do nothing on routes without one, such as stateless API routes.
Set `cooldown.store` to `cache` to keep them in a cache store instead:

```php
'cooldown' => [
    'store' => 'cache',
    'cache' => [
        'store' => 'redis', // null uses the default cache store
    ],
],
```

To add a store, implement `Cooldowns\Contracts\CooldownStore` and register it with
`CooldownManager::extend()` in a service provider.
