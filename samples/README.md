# Samples

Real-world scenarios built with Eloquent Viewable. The [README](../README.md) documents every feature on its own; a
sample combines several of them to solve one problem, and explains why it is put together that way.

| Sample                                | Shows                                                                                                                        |
|---------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| [Trending articles](TrendingArticles) | A "trending this week" list: cooldowns, `orderByViews()` over a period, and caching a ranking that `remember()` cannot cache |
| [Listing stats](ListingStats)         | A seller's stats page: `countByInterval()` for a daily chart, `unique()` visitors, and periods that `remember()` can cache   |
| [Popular products](PopularProducts)   | A "most viewed" catalog sort: queued recording and a `views_count` column kept up to date by `ViewRecorded`                  |
| [Breaking news](BreakingNews)         | Story pages under a traffic spike: the `redis` store, a scheduled flush through `Flusher`, and a dashboard that says how fresh its counts are |

Each sample is a folder you can read top to bottom: a `README.md` with the scenario, the code, the migration for its
tables and a Pest test. The tests run with the rest of the suite, so a sample cannot drift from the package without the
build failing.

```bash
make test-samples
```

The code uses the `CyrildeWit\EloquentViewable\Samples` namespace so it can be autoloaded in this repository. Copy it
into your application under your own namespace. The `samples` directory is not part of the package you install.
