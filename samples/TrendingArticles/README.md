# Trending articles

A blog shows a "Trending this week" sidebar on every page: the ten articles readers are turning to right now, each
with its views over the past seven days.

## The pieces

| File                                                                         | Role                                               |
|------------------------------------------------------------------------------|----------------------------------------------------|
| [`Article.php`](Article.php)                                                 | The viewable model                                 |
| [`ShowArticle.php`](ShowArticle.php)                                         | The controller that shows the article              |
| [`TrendingArticles.php`](TrendingArticles.php)                               | Ranks the articles and remembers the ranking       |
| [`create_articles_table.php`](database/migrations/create_articles_table.php) | The `articles` table                               |
| [`TrendingArticlesTest.php`](TrendingArticlesTest.php)                       | The behaviour below, as tests                      |

Register the controller, with the `views` middleware recording a view with a cooldown, and render the list:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

Route::get('/articles/{article}', ShowArticle::class)
    ->middleware(RecordViews::using('article', cooldown: 30));
```

```blade
@foreach (app(TrendingArticles::class)->get() as $entry)
    <a href="/articles/{{ $entry->viewable->id }}">{{ $entry->viewable->title }}</a> · {{ $entry->count }} views this week
@endforeach
```

## Decisions

**Record from the route, with a cooldown.** A reader who refreshes the page ten times should not put an article in
the list. The `views` middleware records the `{article}` parameter with a 30 minute cooldown, so a reader counts once per
half hour. `unique()` would be the alternative, but a cooldown keeps the counts meaningful for other uses too, and it
stops the views table from growing with every refresh. The middleware records only a successful `GET`, so a mistyped
article id that ends in a 404 counts for nothing, and the controller has no tracking code to forget.

**Rank by decay, not by count.** `views(Article::class)->period(Period::pastDays(7))->trending()` weighs every view by
its age: a view from now counts fully, and one loses half its weight every day. An article that took off this morning
ranks above one that was busy five days ago, even with fewer views, and a spike fades out gradually instead of
dropping off the list all at once when it leaves the window. `orderByViews()` over the same week would count every
view the same and keep last week's hit on top until the window passed it. The period still caps the window at a
week, so the "views this week" label next to each article is `$entry->count`, and articles with no views in the week
are left out rather than padding the list. Keep `$entry->score` for sorting or thresholds; it reads as "worth this
many views right now", not as a view count.

**Remember the ranking for ten minutes.** `trending()` reads every view in the window, and with the sidebar on every
page that query would run on every request. `remember(10)` keeps the ranking for ten minutes, which is fine for a
sidebar. It remembers the ids, counts and scores only, and loads the articles fresh, so an edited title or a deleted
article shows straight away. Give `remember()` a lifetime: a ranking remembered forever never changes.

**Index the views table.** The ranking reads each article's views by `viewable_type`, `viewable_id` and `viewed_at`,
which is exactly the `views_viewable_viewed_at_index` composite index from the migration. See
[database indexes](../../README.md#database-indexes).

## Where to take it next

- Rank by distinct readers with `unique()`, which counts a reader once per hour.
- Make a slower list, such as "popular this month", with a longer half-life: `trending(halfLife: CarbonInterval::week())`.
- Paginate the full list with `Article::orderByTrending(period: Period::pastDays(7))->paginate()`.
- Rank per section with a [view collection](../../README.md#view-collections), recording with
  `RecordViews::using('article', collection: 'sidebar', cooldown: 30)`.
- On a high-traffic site, [queue the recording](../../README.md#queueing-view-recording) with `queue: true` so the
  insert leaves the request. The cooldown is still checked during the request.
