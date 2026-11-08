---
title: "Erasing one person's views"
description: "For a deleted account or a GDPR request, HasViewHistory erases or exports everything one viewer recorded."
sidebar:
  order: 3
---

For a deleted account or a GDPR request, `HasViewHistory` erases or exports everything one viewer recorded:

```php
$user->forgetViewHistory();                          // delete their views
$user->forgetViewHistory(includeGuestViews: true);   // and the guest views of the browsers they signed in on
$user->anonymiseViewHistory();                       // keep the counts, take out what ties the views to them
$user->exportViewHistory();                          // their views, for a data access request
```

There is no foreign key, so a deleted user's views keep pointing at a model that is gone. Call one of these before
deleting the user, or name the viewer afterwards from the command line. A guest is named by their visitor id, the value
of the visitor cookie:

```bash
php artisan views:forget-viewer "App\Models\User" 42
php artisan views:forget-viewer user 42 --with-visitors   # a morph alias works too
php artisan views:forget-visitor 5f2a…
```

Both commands take `--chunk` and ask for confirmation in production unless given `--force`. In code,
`Erasure\Subject::viewerKey($type, $key)` and `Subject::visitor($id)` name the same people for the actions in
`Erasure\Actions`.

`exportViewHistory()` returns a lazy collection of the views, oldest first, so a long history is read in chunks.
`dimensions` holds the value of every [dimension](/eloquent-viewable/viewers/dimensions/) with a column, and is empty without one:

```php
$user->exportViewHistory()->toJson();
// [{"viewable_type":"App\\Models\\Post","viewable_id":12,"collection":null,"context":{"source":"newsletter"},"dimensions":{"device":"mobile"},"viewed_at":"2026-10-01T09:12:00+00:00"}]
```

What to know:

- **A viewer is also found by its visitor id.** With `visitor.identity` set to `viewer` or `fingerprint`, the visitor id
  of a signed-in view is derived from the account. Views whose viewer columns were cleared by hand are still found.
- **Guest views are kept by default.** With the default cookie identity, a user's views share the cookie id with the
  views that browser made before they signed in. `includeGuestViews` and `--with-visitors` delete those as well, which
  on a shared computer can be someone else's.
- **Anonymising works like [retention](/eloquent-viewable/scaling/retention/).** `viewer` and `context` become `null`, and `visitor` is re-hashed
  under a salt per day that is thrown away afterwards. Total views and daily unique visitors stay the same; unique
  counts across days count the person once per day.
- **Buffered views are reached too.** With the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/), the person's views that have
  not been flushed yet are landed first. A [queued](/eloquent-viewable/recording/queueing/) view still in the queue lands afterwards.
- **Rollups are left alone.** [Rollups](/eloquent-viewable/scaling/rollups/) hold counts per bucket and no visitor or viewer, so a forgotten
  person's views stay counted in the history they were folded into. Counts read through the `rollup` source include
  them for every bucket folded before the erasure.
- **The counts of the models they viewed are brought up to date.** Their remembered counts are forgotten and their
  [counter columns](/eloquent-viewable/scaling/counts-table/) recounted right away. Past 100 models, every remembered count
  is forgotten and the next `views:recount` recounts every model.
- **Each call dispatches an event** for your audit log, also when the person had no views:
  `Erasure\Events\ViewHistoryForgotten` and `ViewHistoryAnonymised` with the `subject` and the number of `views`, and
  `ViewHistoryExported` with the `subject`.
