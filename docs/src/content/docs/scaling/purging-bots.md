---
title: "Purging bot views"
description: "Delete the views that bursts of bot traffic recorded before the guard stopped them."
sidebar:
  order: 6
---

The [`IgnoreBursts`](/eloquent-viewable/extending/guards/) guard stops a burst once it reaches the limit. `views:purge-bots` deletes the views that were recorded
anyway: the first views of a burst, and the views of tables that filled up before the guard existed.

```bash
php artisan views:purge-bots --since=30d --dry-run
php artisan views:purge-bots --since=30d
```

It reads the views since `--since`, a duration such as `7d` or a date such as `2026-01-01` and `1d` by default, in the
order they were viewed, and finds every visitor that opened more than `recording.bursts.max` different models within
`recording.bursts.seconds`. Pass `--max` and `--seconds` to apply another rule.

The command is built to leave real views alone:

- **Only the views inside a burst are deleted.** A visitor's other views stay. A person who opens a handful of search
  results in tabs and then reads them keeps the reading.
- **Views of a signed-in viewer are kept**, because an account is almost always a person. Pass `--include-viewers` to
  delete those as well.
- **`--whole-visitor` deletes every view of a visitor** with at least `--min-bursts` separate bursts, 3 by default. It
  refuses to run with the `fingerprint` identity, because people on one network with the same browser share a visitor
  id there.
- **It asks before deleting in production.** Pass `--force` to skip the question, for example in the scheduler, and
  `--dry-run` to count the views first.

What to know:

- **The deleted views leave the rollups too.** Once views are deleted, the [rollups](/eloquent-viewable/scaling/rollups/) are folded again from the
  first of them. Views before the point where a rollup can no longer be folded again, because they are pruned, or
  anonymised for a month or year tier, are left alone, and the command says where it started.
- **Only the stored visitor id is read.** A bot that drops its cookie looks like many visitors with one view each, so
  the command cannot find it afterwards. The `network` count of `IgnoreBursts` stops it while it records.
- **Views still in the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/) are not read.** Run `views:flush` first.
- **Deleting cannot be undone.** Run a dry run, or take a backup, before the first purge of a large table.
- **A run forgets every remembered count** when it deleted a view, and dispatches `Retention\Events\BotViewsPurged`.
  [Counter columns](/eloquent-viewable/scaling/counts-table/) are not updated; the command reminds you to run
  `views:recount`.
