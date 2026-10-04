# Content dashboard

A publisher puts out written guides and podcast episodes. The editors want one page that answers "what worked this
week": the most viewed pieces of either kind, how each kind is doing against the week before, where readers came
from, and how the latest guides are getting on. They switch between a week, a quarter or a range of dates by changing
the URL.

## The pieces

| File                                                                                                 | Role                                   |
|------------------------------------------------------------------------------------------------------|----------------------------------------|
| [`Guide.php`](Guide.php)                                                                             | A viewable model                       |
| [`Episode.php`](Episode.php)                                                                         | Another viewable model                 |
| [`ShowDashboard.php`](ShowDashboard.php)                                                             | The controller, which takes the period |
| [`ContentDashboard.php`](ContentDashboard.php)                                                       | Counts everything on the page          |
| [`DashboardReport.php`](DashboardReport.php)                                                         | What the page shows, as JSON           |
| [`create_guides_and_episodes_tables.php`](database/migrations/create_guides_and_episodes_tables.php) | The `guides` and `episodes` tables     |
| [`ContentDashboardTest.php`](ContentDashboardTest.php)                                               | The behaviour below, as tests          |

Views are recorded on the guide and episode pages, with the placement a reader came from as the
[collection](../../README.md#view-collections), for example `views($guide)->collection('newsletter')->record()` behind
the newsletter's links. Register the dashboard behind your editors' middleware:

```php
Route::get('/dashboard/{period}', ShowDashboard::class)
    ->middleware(['web', 'auth'])
    ->name('dashboard');
```

`/dashboard/7d` returns:

```json
{
    "period": "7d",
    "top": [
        {"rank": 1, "type": "Episode", "title": "Postgres at scale", "views": 5},
        {"rank": 2, "type": "Guide", "title": "Indexing JSON columns", "views": 3}
    ],
    "trends": {
        "guides": {"current": 3, "previous": 1, "delta": 2, "percent": 200.0},
        "episodes": {"current": 2, "previous": 0, "delta": 2, "percent": null}
    },
    "placements": {"newsletter": 7, "direct": 2, "search": 1},
    "latest_guides": [
        {"title": "Naming things", "views": 0},
        {"title": "Indexing JSON columns", "views": 3}
    ]
}
```

## Decisions

**Let the URL carry the period.** `Period` binds to a route parameter like a model, so the controller takes a `Period`
and the router parses `7d`, `3m` or `2026-09-01..2026-10-01` into one. An editor can bookmark or share a view of the
dashboard, and a segment that does not parse is a 404 before the controller runs. `getRouteKey()` writes the same
string back, so links to the previous or next range use `route('dashboard', $period)`.

**Count on the editors' clock.** The application stores `viewed_at` in UTC, but "the past 7 days" should start at
midnight in Amsterdam, where the editors are. `timezone()` re-anchors a relative period on that clock, so the
window starts at 22:00 UTC in summer, and every count on the page passes through the same builder in
`ContentDashboard::query()`. A range of dates in the URL is taken as written in the application's timezone.

**Rank every type at once.** `Views::top()` ranks across all viewable types in one grouped query, then loads the models
with one query per type. Asking each type for its own top ten and merging them would need a query per type and still
get the order wrong when one type has more than ten entries above the other's best. A deleted guide drops out of the
ranking, so it can hold fewer than ten entries.

**Compare per type, and only when it can.** `compare()` counts the period and the one before it, of the same width,
which is what "against last week" means. A period open on one side, such as `2026-09-01..`, has no width, and the
dashboard shows no trend rather than an error. A type without views in the previous period has no percentage, because
growth from nothing has none; the page shows the delta instead.

**Name the default placement.** `countByCollection()` reports views without a collection under the empty string. The
dashboard adds up both types and calls those views `direct`, which is what they are on this site: someone typed the URL
or followed a link that was not tagged.

**Count the rows you already have.** The "latest guides" table is ordinary Eloquent, and calling `views($guide)->count()`
for each row would run twenty queries. `forViewables($guides)->counts()` counts them in one, and returns zero for a
guide without views, so the table needs no special case.

**Remember for ten minutes.** Every count goes through `remember(10)`. A relative period such as `7d` has a cache key
that does not move with the clock, so the dashboard is counted once per period every ten minutes however many editors
have it open. `top()` caches the ranking and loads the models fresh, so an edited title shows straight away.

## Where to take it next

- Add a chart of the period with `countByInterval(Granularity::Day)`, which takes the same `timezone()` and buckets the
  days on the editors' clock. The [listing stats](../ListingStats) sample builds one.
- Rank by readers instead of views with `unique()` on the builder, which `top()`, `compare()` and `counts()` all honour.
- Clear a remembered count after an editor fixes a mistake, such as views recorded twice by a misconfigured link, with
  `views($guide)->forgetCache()`.
