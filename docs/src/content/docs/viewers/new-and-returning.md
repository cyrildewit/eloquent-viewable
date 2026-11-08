---
title: "New and returning visitors"
description: "returning() counts the visitors who came back, and countByFrequency() how many visitors viewed on one day, on two, and so on."
sidebar:
  order: 2
---

`returning()` counts the visitors who came back, and `countByFrequency()` how many visitors viewed on one day, on two,
and so on. On course content and documentation, coming back is often the number that matters:

```php
views($post)->period(Period::pastDays(30))->returning()->count();   // visitors who viewed on two days or more
views($post)->period(Period::pastDays(30))->returning()->compare(); // the same, against the 30 days before

$frequency = views($post)->period(Period::pastDays(30))->countByFrequency();

$frequency->toArray();        // [1 => 820, 2 => 140, '3+' => 60]
$frequency->new();            // 820, viewed on one day
$frequency->returning();      // 200, viewed on two days or more
$frequency->total();          // 1020
$frequency->returningShare(); // 0.196, or null without visitors

views($course)->countByFrequency(upTo: 5); // [1 => ..., 2 => ..., 3 => ..., 4 => ..., '5+' => ...]
```

A visit is a day with at least one view, so reloading a page or reading it twice in one afternoon does not make someone
a returning visitor. Days follow your application timezone, or the one passed to `timezone()`, which needs a period with
a start, like `countByInterval()`.

## Good to know

- **Only the period counts.** A visitor who viewed on two days inside the period is returning. Views before the period
  are not read, so someone who first came last year and once this month is new this month.
- **"The same visitor" follows your [visitor identity](/eloquent-viewable/viewers/who-viewed-what/#counting-one-account-as-one-visitor).** With `fingerprint`, a
  guest gets a new id every day, so every guest counts as new. Signed-in users keep theirs.
- **Anonymised views are left out**, and so are views without a visitor. Their ids no longer link a visitor across
  days, so they would only add to the new visitors. Keep `retention.anonymise.after` longer than the periods you read.
- **It counts visitors.** `unique()` changes nothing. `viewedBy($user)` narrows it to one user, so
  `views($course)->viewedBy($user)->returning()->count()` is 1 when they came back and 0 when they did not.
- **It reads the `views` table**, also on the `rollup` source, because rollups keep no visitors. `remember()` caches it,
  one entry for every cap and for `returning()`.
- **Only `count()` and `compare()` read `returning()`.** Other methods throw `Querying\Exceptions\InvalidReturning`
  instead of ignoring it.

The query groups every view of the model in the period by visitor, so the `visitor` column in the composite index from
[Database indexes](/eloquent-viewable/scaling/indexes/) speeds it up as it does `unique()`.
