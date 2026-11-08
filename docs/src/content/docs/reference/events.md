---
title: Events
description: Every event the package dispatches, when it fires and what it carries.
sidebar:
  order: 3
---

Listen for these like any other event of your application. Every class lives under `CyrildeWit\EloquentViewable`.
Stores write without Eloquent, so listen for `ViewRecorded` rather than for `View` model events.

## Recording

| Event | Fires | Carries |
|-------|-------|---------|
| `Recording\Events\ViewRecorded` | when a view is handed to the store; from the worker when recording is [queued](/eloquent-viewable/recording/queueing/), and before the row exists with the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/) | `record` |
| `Recording\Events\ViewSkipped` | when a [guard](/eloquent-viewable/extending/guards/) refuses a view | `attempt`, `guard` |
| `Recording\Events\ViewAttempted` | after every attempt, stored, queued or skipped, in the request that made it; only built when something listens | `attempt`, `result` |
| `Recording\Events\BurstDetected` | once when `IgnoreBursts` starts blocking a visitor or network | `attempt`, `by` (`visitor` or `network`) |
| `Recording\Events\ViewsDestroyed` | after the views of a model are deleted, by `views($model)->destroy()` or when the model [is deleted](/eloquent-viewable/recording/removing-views-on-delete/) | `viewable` |

See [Finding out why a view was not recorded](/eloquent-viewable/recording/recording-views/#finding-out-why-a-view-was-not-recorded)
for `ViewAttempted` and `ViewSkipped` in practice.

## Milestones and spikes

These are dispatched once the row they report is written, so queue their listeners. Each carries the model's morph
`type` and `key`, and `viewable()` returns the model, or `null` once it is deleted.

| Event | Fires | Carries |
|-------|-------|---------|
| `Milestones\Events\ViewMilestoneReached` | once per model and threshold, when a recount takes a counter column past a [milestone](/eloquent-viewable/counting/milestones/) | `type`, `key`, `column`, `milestone`, `count`, `passed` |
| `Spikes\Events\ViewsSpiked` | once when `views:detect-spikes` finds a model above its [baseline](/eloquent-viewable/rankings/rising-and-spiking/#spike-alerts) | `type`, `key`, `baseline` |
| `Spikes\Events\ViewsDropped` | the same, below its baseline, for the types that watch drops | `type`, `key`, `baseline` |
| `Spikes\Events\ViewsSettled` | once a spike or drop has been back to normal for the cooldown | `type`, `key`, `direction`, `peakCount`, `peakScore`, `since` |

## Erasure

| Event | Fires | Carries |
|-------|-------|---------|
| `Erasure\Events\ViewHistoryForgotten` | after one viewer's or visitor's views are [deleted](/eloquent-viewable/viewers/erasing-views/) | `subject`, `views` |
| `Erasure\Events\ViewHistoryAnonymised` | after one viewer's or visitor's views are anonymised | `subject`, `views` |
| `Erasure\Events\ViewHistoryExported` | after one viewer's or visitor's views are exported | `subject` |

`subject` is an `Erasure\Subject`, with the `viewerType`, `viewerKey` or `visitor` the run was about.

## Retention

Each is dispatched once per run that changed at least one view, and every remembered count is forgotten with it.

| Event | Fires | Carries |
|-------|-------|---------|
| `Retention\Events\ViewsPruned` | after `views:prune` [deletes old views](/eloquent-viewable/scaling/retention/) | `from`, `until`, `views` |
| `Retention\Events\ViewsAnonymised` | after `views:anonymise` anonymises old views | `from`, `until`, `views` |
| `Retention\Events\BotViewsPurged` | after `views:purge-bots` [deletes bot views](/eloquent-viewable/scaling/purging-bots/) | `from`, `until`, `views`, `visitors` |
