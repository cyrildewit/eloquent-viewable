---
title: "Rising, spiking and dropping"
description: "Find what is growing, spiking or dropping against its own usual traffic, and get alerted when it happens."
sidebar:
  order: 3
---

[`trending()`](/eloquent-viewable/rankings/trending/) favours what gets the most views lately. Two other questions come up as often: what is *growing*, and what
is doing something *unusual*. Both compare a model's views in a period with a reference, so a post that is busy every
day doesn't rank just for being busy.

```php
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;

// Growing the most against the day before
views(Post::class)->period(Period::pastDays(1))->rising(10);

// Far above the same hour on the past four Thursdays: taking off
views(Post::class)->period(Period::subHours(1))->anomalies();

// Far below it: a broken link, a page that fell out of search
views(Product::class)->period(Period::subHours(1))->anomalies(threshold: -3);

// One model against its own past
$baseline = views($post)->period(Period::subHours(1))->againstBaseline();
```

Each entry has the usual `rank`, `count` and `viewable`, a `score`, and the `baseline` it was compared with:

```php
foreach (views(Post::class)->period(Period::subHours(1))->anomalies() as $entry) {
    $entry->count;                 // its views in the period, 1,840
    $entry->score;                 // how unusual that is, 52.2
    $entry->baseline->references;  // the same hour on past weeks, [210, 190, 240, 210]
    $entry->baseline->mean;        // 212.5
}
```

## How the ranking is made

- **`rising()`** divides the count by the count of the [previous period](/eloquent-viewable/counting/retrieving-counts/#compare-with-the-previous-period), the same
  window `compare()` uses, and ranks the models that grew, highest first. A post that went from 6 views to 60 scores 10.
  With nothing before, it counts as one view, so growth from nothing stays finite.
- **`anomalies()`** compares the count with the same window on past weeks, or days with
  `seasonality: Seasonality::Day`, `samples` of them, four by default. The score is the z-score: how many standard
  deviations the count lies from their mean. The deviation is at least the square root of the mean, the noise a count of
  that size has anyway, and at least 1, so a post with exactly 10 views every Thursday doesn't spike at 11. The default
  `threshold` of 3 finds spikes; a negative one finds drops, lowest first.
- **`minimum`**, 10 by default, leaves out a model unless it got that many views in the period, or that many on average
  in the reference windows. So 1 to 4 views never ranks, while a page that drops from 400 to 0 does.

Comparing with the same weekday keeps the rhythm of a week out of it: Monday morning is compared with Monday mornings,
not with Sunday night. The period can be as long as the season, a day for `Seasonality::Day` and a week for
`Seasonality::Week`, and starts the windows it is compared with.

## Good to know

- **Use a closed window for alerts.** `Period::subHours(1)` includes the views of the last few minutes, which aren't
  all in yet with a queue or the Redis buffer. `Period::create(now()->subHours(2)->startOfHour(), now()->subHour()->startOfHour())`
  is the last complete hour. [`views:detect-spikes`](/eloquent-viewable/rankings/rising-and-spiking/#spike-alerts) does this for you.
- **Give `remember()` a lifetime.** A remembered ranking keeps its windows, so `remember(10)` serves the same answer for
  ten minutes.
- **`unique()` compares visitors instead of views.**

## Spike alerts

To hear about a spike rather than look for one, list the models to watch and schedule the detector:

```php
'spikes' => [
    'types' => [
        Post::class => [
            'window' => '1h',        // whole hours, or `1d`
            'seasonality' => 'week', // or `day`
            'samples' => 4,
            'threshold' => 3.0,
            'minimum' => 50,
            'drops' => true,         // off by default
            'cooldown' => '6h',
        ],
    ],
],
```

```php
// routes/console.php
Schedule::command('views:detect-spikes')->hourly()->onOneServer();
```

Every option may be left out for the default shown, except `drops`, which is `false`. Each run looks at the last
closed window, the hour before the current one began, and runs `anomalies()` over it. A model that leaves its baseline
dispatches `ViewsSpiked`, or `ViewsDropped`, once. While it stays out, nothing fires again and the `view_spikes` table
keeps its peak. Once it has been back to normal for the `cooldown`, `ViewsSettled` fires with the peak count, score and
the moment it started:

```php
use CyrildeWit\EloquentViewable\Spikes\Events\ViewsSpiked;

class TellTheNewsDesk implements ShouldQueue
{
    public function handle(ViewsSpiked $event): void
    {
        $post = $event->viewable(); // null once deleted

        $post?->desk->notify(new StoryTakingOff($post, $event->baseline));
    }
}
```

Like [milestones](/eloquent-viewable/counting/milestones/), the events carry the model's `type` and `key` with `viewable()` and `is()`, and are
dispatched once the row they report is written, so queue your listeners. Spikes and drops are scored from the same read
of the windows, so watching drops costs nothing extra; turn it on for the types where a drop means something broke.
Add the [`(viewable_type, viewed_at)` index](/eloquent-viewable/scaling/indexes/) for every watched type.

## On large tables

Both rankings count every model of the type in every window, in one statement, so a ranking reads one window more than
it has samples. Within one type, add the optional [`(viewable_type, viewed_at)` index](/eloquent-viewable/scaling/indexes/): without it,
every window walks the composite index once per model. On a million views on Postgres, the last hour of articles
against four past weeks took about 45 ms without it and 1 ms with it, and the last day 49 ms and 4 ms. `views:doctor`
recommends it once `spikes.types` is set. Across every type, `Views::anomalies()`, the `viewed_at` index the migration
creates serves each window.

With the [`rollup` source](/eloquent-viewable/scaling/rollups/), windows whose views are gone read the rollups, so keep an `hour` tier for as long
as `samples` weeks reach back to compare hours, and a `day` tier for days.
