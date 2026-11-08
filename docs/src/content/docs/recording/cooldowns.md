---
title: "Cooldowns"
description: "A cooldown ignores repeated views of the same model by the same visitor for a number of minutes, or until a given time."
sidebar:
  order: 2
---

Without a cooldown, every page load is a view. A reader who refreshes a post five times, or goes back and forth between
two posts, adds five views. A cooldown ignores repeated views of the same model by the same visitor for a while, so the
count reflects visits rather than page loads.

## Adding a cooldown

Pass the number of minutes, or the time it should run until:

```php
views($post)->cooldown(30)->record();                  // 30 minutes
views($post)->cooldown(now()->addHours(3))->record();   // 3 hours
views($post)->cooldown(now()->endOfDay())->record();   // at most one view per visitor per day
```

On a route, pass it to the middleware:

```php
Route::get('/posts/{post}', ShowPost::class)
    ->middleware(RecordViews::using('post', cooldown: 30));
```

## What happens during a cooldown

With a 30 minute cooldown, a reader who opens the same post four times is recorded twice:

| Time  | What the reader does    | Recorded? | Why                                   |
|-------|-------------------------|-----------|---------------------------------------|
| 10:00 | Opens the post          | Yes       | Starts a cooldown until 10:30         |
| 10:05 | Refreshes the page      | No        | The cooldown runs until 10:30         |
| 10:20 | Comes back from Google  | No        | The cooldown still runs until 10:30   |
| 10:45 | Opens the post again    | Yes       | Starts a new cooldown until 11:15     |

A skipped view does not extend the cooldown: it runs from the last view that was recorded.

The cooldown is kept per model, per [collection](/eloquent-viewable/recording/collections/) and per visitor. Opening
another post, or the same post in another collection, is recorded as usual.

While a cooldown runs, `record()` returns `false`. To check why a view was skipped, use `attempt()`:

```php
use CyrildeWit\EloquentViewable\Recording\Guards\EnforceCooldown;

$result = views($post)->cooldown(30)->attempt();

$result->wasSkippedBy(EnforceCooldown::class); // true at 10:05 and 10:20
```

## Where cooldowns are kept

Cooldowns are kept in the visitor's **session** by default. That works on any route in the `web` middleware group, but:

- on a route without a session, such as a stateless API route, the cooldown does nothing;
- when the session expires, its cooldowns go with it;
- a reader who switches devices, or clears their cookies, starts afresh.

Set `cooldown.store` to `cache` to keep them in a cache store instead:

```php
'cooldown' => [
    'store' => 'cache',
    'cache' => [
        'store' => 'redis', // null uses the default cache store
    ],
],
```

This works without a session. Use a cache store that all your servers share, such as Redis, rather than `file` or
`array`. With [`visitor.identity`](/eloquent-viewable/viewers/who-viewed-what/#counting-one-account-as-one-visitor) set to `viewer`, a signed-in reader is one visitor on
every device, so the cooldown follows their account.

:::note
Two requests that arrive at the same moment can both be recorded, because neither has started the cooldown yet when
the other checks it. That is rare in practice, but don't rely on a cooldown for exact counts.
:::

## Checking your setup

Cooldowns are enforced by the `EnforceCooldown` guard in `recording.guards`, which is listed by default. If you remove
it, `cooldown()` does nothing. [`php artisan views:doctor`](/eloquent-viewable/scaling/checking-your-setup/) warns you
when that is the case.

## Adding a store

To keep cooldowns somewhere else, implement `Cooldowns\Contracts\CooldownStore`, which has two methods:

```php
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use DateTimeInterface;

final class DatabaseCooldownStore implements CooldownStore
{
    public function has(string $key): bool
    {
        return DB::table('view_cooldowns')
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function put(string $key, DateTimeInterface $expiresAt): void
    {
        DB::table('view_cooldowns')->upsert(
            ['key' => $key, 'expires_at' => $expiresAt],
            ['key'],
            ['expires_at'],
        );
    }
}
```

Register it in a service provider and select it with `'store' => 'database'`:

```php
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;

$this->app->make(CooldownManager::class)->extend('database', fn ($app): CooldownStore => new DatabaseCooldownStore);
```
