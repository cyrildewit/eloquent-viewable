---
title: "Checking your setup"
description: "views:doctor looks at the setup and says what to fix."
sidebar:
  order: 1
---

`views:doctor` looks at the setup and says what to fix. Run it after installing, after changing the config and on the
servers that record views, since it reads their cache, scheduler and Redis:

```bash
php artisan views:doctor
```

```text
  Shared cache stores
  ✗ Cooldowns: kept in the session, whose `array` driver forgets them after every request.
    → Use another session driver, or set `cooldown.store` to `cache`.

  Scheduler
  ! `views:maintain` is not scheduled, so rollups, retention and counter columns are not kept up to date.
    → Add `Schedule::command('views:maintain')->hourly()->onOneServer();` to `routes/console.php`, unless a crontab of your own runs it.

   ERROR  1 failure, 1 warning, 0 suggestions.
```

| Check               | Key               | Looks at                                                                                                     |
|---------------------|-------------------|--------------------------------------------------------------------------------------------------------------|
| Database schema     | `schema`          | the views table, its columns and indexes, and the retention, rollup and counter columns in use               |
| Optional indexes    | `index-advice`    | the [optional indexes](/eloquent-viewable/scaling/indexes/) the config relies on or a large table would use                    |
| Rollups             | `rollups`         | that every rollup counts the views it should, which anonymising and pruning wait for                         |
| Shared cache stores | `shared-cache`    | that cooldowns, the throttle, the burst guard, the fingerprint salt and remembered counts use a shared store |
| Scheduler           | `schedule`        | that `views:maintain` or `MaintainViewsJob`, and `views:flush`, are scheduled when something needs them      |
| Trusted proxies     | `trusted-proxies` | that a proxy is trusted when the `fingerprint` identity, `IgnoreIpAddresses` or `IgnoreBursts` reads the IP  |
| Redis stream        | `redis-stream`    | the server and its version, how long buffered views wait, and views a flush took without acknowledging       |
| Refused attempts    | `crawler-share`   | the share of attempts each guard refused, once sampling is on                                                |
| Configuration       | `configuration`   | settings that undo each other, such as queueing on the `sync` connection                                     |

A failure means something does not work, a warning that it breaks under load or on more than one server, and a
suggestion that it could be better. The command fails on a failure, so it can run in a deploy. `--strict` fails on a
warning as well, `--only=schema --only=schedule` runs some of the checks and `--json` prints the findings for a script.

The console cannot see the queries an app runs or the requests it serves, so some checks read what the config implies.
The scheduler check reads the Laravel scheduler, so a command run from a crontab of your own shows as missing. The
proxies check reads the proxies `trustProxies(at:)` configures in `bootstrap/app.php`.

The share of attempts each guard refuses is only known while it is counted. Turn sampling on to count them per day in
the cache for a week:

```php
'doctor' => [
    'sample' => [
        'enabled' => true,
        'store' => 'redis',
        'crawler_share' => 0.5,
    ],
],
```

That adds two cache calls to every attempt, in a store every server must share. The doctor then warns when
`IgnoreCrawlers` refused more than `crawler_share` of the attempts, which usually means a client of your own, such as the
webview of an app, is taken for a crawler, or a scraper is crawling the site.
