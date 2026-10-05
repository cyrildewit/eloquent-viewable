# Samples

Real-world scenarios built with Eloquent Viewable. The [README](../README.md) documents every feature on its own; a
sample combines several of them to solve one problem, and explains why it is put together that way.

| Sample                                           | Shows                                                                                                                                                                        |
|--------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [Trending articles](TrendingArticles)            | A "trending this week" list: the `views` route middleware with a cooldown, `trending()` weighing views by their age, and `remember()` on the ranking                         |
| [Listing stats](ListingStats)                    | A seller's stats page: `countByInterval()` for a daily chart, `unique()` visitors, `compare()` with the 30 days before, and periods that `remember()` can cache              |
| [Popular products](PopularProducts)              | A "most viewed" catalog sort: queued recording and a `views_count` column kept up to date by `ViewRecorded`                                                                  |
| [Breaking news](BreakingNews)                    | Story pages under a traffic spike: the `redis` store, a scheduled flush through `Flusher`, and a dashboard that says how fresh its counts are                                |
| [Recently viewed](RecentlyViewed)                | "Continue where you left off" for signed-in learners: the viewer columns, `HasViewHistory`, `whereNotViewedBy()` and the `viewer` identity                                   |
| [Content dashboard](ContentDashboard)            | An editors' dashboard over two content types: `Views::top()`, `compare()`, `countByCollection()`, `forViewables()->counts()`, a `Period` bound from the URL and `timezone()` |
| [Privacy-first analytics](PrivacyFirstAnalytics) | Counting docs readers without cookies: the `fingerprint` identity, the privacy guards, a guard of your own, `attempt()`, `ViewSkipped`, `context()` and `Views::fake()`      |

Each sample is a folder you can read top to bottom: a `README.md` with the scenario, the code, the migration for its
tables and a Pest test. The tests run with the rest of the suite, so a sample cannot drift from the package without the
build failing.

```bash
make test-samples
```

The code uses the `CyrildeWit\EloquentViewable\Samples` namespace so it can be autoloaded in this repository. Copy it
into your application under your own namespace. The `samples` directory is not part of the package you install.
