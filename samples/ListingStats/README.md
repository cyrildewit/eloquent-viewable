# Listing stats

A marketplace gives each seller a stats page for their listing: a chart of the views per day over the past 30 days,
the number of people who saw it, and how the views compare with the 30 days before.

## The pieces

| File                                                                         | Role                                          |
|------------------------------------------------------------------------------|-----------------------------------------------|
| [`Listing.php`](Listing.php)                                                 | The viewable model                            |
| [`ListingStats.php`](ListingStats.php)                                       | Counts the views and caches each count        |
| [`ListingReport.php`](ListingReport.php)                                     | The numbers the page shows                    |
| [`create_listings_table.php`](database/migrations/create_listings_table.php) | The `listings` table                          |
| [`ListingStatsTest.php`](ListingStatsTest.php)                               | The behaviour below, as tests                 |

Views are recorded on the listing page the usual way; see the [trending articles](../TrendingArticles) sample for a
controller that does so with a cooldown. Build the report and render it:

```php
$report = app(ListingStats::class)->for($listing);
```

```blade
<p>
    {{ $report->totalViews() }} views by {{ $report->visitors }} people
    @if ($report->change() !== null)
        ({{ sprintf('%+d', $report->change()) }}% on the 30 days before)
    @endif
</p>

@foreach ($report->views as $day)
    <div title="{{ $day->start->toFormattedDayDateString() }}" style="height: {{ $day->count }}px"></div>
@endforeach
```

## Decisions

**One query per chart, not one per day.** `countByInterval(Granularity::Day)` groups the views by day in the database
and fills the days without views with zero, so the chart always has 30 bars and needs one query. Looping over the days
and calling `count()` for each would run 30.

**Start the window at midnight.** `remember()` builds its cache key from the period, and an absolute period is
identified by its timestamps. A window that starts 30 days before *now* gets a new key every second, so nothing would
ever be read back from the cache. Starting at midnight keeps the key the same all day and gives a new one at midnight,
which is exactly when the chart should gain a bar for the new day. That also means the comparison is not like for
like: today so far is compared with a full day 30 days ago. Comparing up to the same time of day would bring back the
moving key.

**Count the people once.** A person who views the listing on three days is one of the people who saw it, but a
visitor on each of those days. `visitors` is a `unique()` count over the whole window; `visitorsPerDay` is the unique
series. Their numbers differ, so the page should not show the sum of the chart as the number of people.

**Cache each window for as long as it can change.** The current window ends with today, which keeps changing, so it is
cached for ten minutes. Views are recorded at the current time, so a window that has ended cannot change. The previous
window is cached until midnight, when its key goes out of use anyway.

**`remember()` does the caching.** Unlike the ranking in the trending articles sample, `count()` and
`countByInterval()` go through `remember()`, so `ListingStats` needs no cache of its own.

## Where to take it next

- Let the seller click a bar to see that day by the hour. `$day->period()` is the bucket as a period, so
  `views($listing)->period($day->period())->countByInterval(Granularity::Hour)` adds up to the bar.
- Offer a year view with `Granularity::Week` or `Granularity::Month`. A finer granularity over a long range can produce
  more buckets than `max_intervals` allows, which throws `InvalidInterval` before the query runs.
- Show where the views came from by recording them into [view collections](../../README.md#view-collections) and
  passing `collection()` to each count.
