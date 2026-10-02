# Popular products

A shop with a large catalog lets shoppers sort any category by "Most viewed". The sort has to stay fast with millions of
recorded views, and recording a view must not slow down the product page.

## The pieces

| File                                                                         | Role                                                    |
|------------------------------------------------------------------------------|---------------------------------------------------------|
| [`Product.php`](Product.php)                                                 | The viewable model, with its `views_count` column       |
| [`ShowProduct.php`](ShowProduct.php)                                         | The controller that queues a view with a cooldown       |
| [`CountProductView.php`](CountProductView.php)                               | The `ViewRecorded` listener that adds to the counter    |
| [`RecountProductViews.php`](RecountProductViews.php)                         | Sets the counters back to the views table               |
| [`create_products_table.php`](database/migrations/create_products_table.php) | The `products` table                                    |
| [`PopularProductsTest.php`](PopularProductsTest.php)                         | The behaviour below, as tests                           |

Register the controller and the listener, for example in `AppServiceProvider::boot()`:

```php
Route::get('/products/{product}', ShowProduct::class);

Event::listen(ViewRecorded::class, CountProductView::class);
```

Sort on the counter like any other column:

```php
$products = $category->products()->mostViewed()->paginate(24);
```

Schedule the recount for a quiet hour:

```php
Schedule::call(new RecountProductViews)->weekly();
```

## Decisions

**Store the count on the product.** `orderByViews()` counts the views of every product in the category on each call,
so the sort gets slower as the views table grows, and a paginated catalog cannot be cached as one result the way the
[trending articles](../TrendingArticles) sample caches its top ten. A `views_count` column with an index turns the sort
into an ordinary indexed `ORDER BY`. The views table still has every view, so `views($product)->period(...)->count()`
and charts keep working.

**Keep it up to date from the event.** `ViewRecorded` is dispatched once the store has accepted the view, by the same
`RecordView` action whether the view was recorded during the request or by a queued job. Listening to it means the
counter cannot count a view a guard refused: a shopper on cooldown, or a crawler once `IgnoreCrawlers` is listed,
never gets there. The event carries the `ViewRecord`, not a row, so the listener reads the viewable from it. The
listener adds one with a single `UPDATE ... SET views_count = views_count + 1`, so workers storing views of the same
product at the same time do not overwrite each other. It goes through `toBase()` because the Eloquent builder's
`increment()` also sets `updated_at`, and a view is not an edit.

**Queue the recording.** `queue()` moves the insert and the counter update to a queue worker, so the product page does
two fewer writes. The cooldown is still checked during the request, so a shopper on cooldown never puts a job on the
queue. The listener itself is not queued: it already runs in the worker, and a second job per view would only add load.
It must not read the request, the session or the signed-in user, because there are none in the worker; the
[README](../../README.md#queueing-view-recording) explains why.

**Count only what the column means.** Every viewable type fires the same event, and views recorded into a
[collection](../../README.md#view-collections) are a different kind of view. The listener checks the type and skips
collections, and the recount counts the same views, so the two agree.

**Recount now and then.** The counter is a copy, and a copy drifts: a worker that dies between the insert and the
update, views removed with `destroy()`, or a column added to a table that already had views. `RecountProductViews`
counts each product's views in chunks and fixes the counters that are off. A view stored while a chunk is being
recounted can be missed until the next run, which is why it belongs at a quiet hour and not in the request.

## Where to take it next

- Show the count on the product card straight from `$product->views_count`, without a query per card.
- Add `views_this_week` next to it for a "Popular this week" sort, recounted nightly with
  `views($product)->period(Period::pastDays(7))->count()`. Unlike an all-time count it goes down as well as up, so an
  increment alone cannot keep it right.
- Prune old rows from the views table to keep it small. The counter keeps the all-time count, but the recount would then
  lower it, so stop running it or only recount the views since the prune.
