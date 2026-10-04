# Trending articles

A blog shows a "Trending this week" sidebar on every page: the ten articles with the most views over the past seven
days, each with its view count.

## The pieces

| File                                                                         | Role                                               |
|------------------------------------------------------------------------------|----------------------------------------------------|
| [`Article.php`](Article.php)                                                 | The viewable model                                 |
| [`ShowArticle.php`](ShowArticle.php)                                         | The controller that shows the article              |
| [`TrendingArticles.php`](TrendingArticles.php)                               | Ranks the articles and caches the ranking          |
| [`create_articles_table.php`](database/migrations/create_articles_table.php) | The `articles` table                               |
| [`TrendingArticlesTest.php`](TrendingArticlesTest.php)                       | The behaviour below, as tests                      |

Register the controller, with the `views` middleware recording a view with a cooldown, and render the list:

```php
use CyrildeWit\EloquentViewable\Http\Middleware\RecordViews;

Route::get('/articles/{article}', ShowArticle::class)
    ->middleware(RecordViews::using('article', cooldown: 30));
```

```blade
@foreach (app(TrendingArticles::class)->get() as $article)
    <a href="/articles/{{ $article->id }}">{{ $article->title }}</a> · {{ $article->views_count }} views
@endforeach
```

## Decisions

**Record from the route, with a cooldown.** A reader who refreshes the page ten times should not put an article in
the list. The `views` middleware records the `{article}` parameter with a 30 minute cooldown, so a reader counts once per
half hour. `unique()` would be the alternative, but a cooldown keeps the counts meaningful for other uses too, and it
stops the views table from growing with every refresh. The middleware records only a successful `GET`, so a mistyped
article id that ends in a 404 counts for nothing, and the controller has no tracking code to forget.

**Rank inside the database.** `orderByViews('desc', Period::pastDays(7))` adds a `views_count` subquery limited to the
period and sorts on it, so only the top ten rows come back. Views older than the window drop out on their own; there is
no counter to reset. Articles with no views in the window are left out rather than padding the list.

**Cache the ranking, not the models.** `remember()` only caches `count()` and `countByInterval()`, not the
`orderByViews()` scope, and the scope counts every view in the window on each call. With the sidebar on every page that
query runs on every request, so `TrendingArticles` caches the result for ten minutes. It caches the ids and counts only
and loads the models fresh, so an edited title or a deleted article shows straight away. The counts are up to ten
minutes behind, which is fine for a sidebar.

**Index the views table.** The ranking counts each article's views by `viewable_type`, `viewable_id` and `viewed_at`,
which is exactly the `views_viewable_viewed_at_index` composite index from the migration. See
[database indexes](../../README.md#database-indexes).

## Where to take it next

- Use `orderByUniqueViews()` instead to rank by distinct readers.
- Rank per section with a [view collection](../../README.md#view-collections), recording with
  `RecordViews::using('article', collection: 'sidebar', cooldown: 30)`.
- On a high-traffic site, [queue the recording](../../README.md#queueing-view-recording) with `queue: true` so the
  insert leaves the request. The cooldown is still checked during the request.
