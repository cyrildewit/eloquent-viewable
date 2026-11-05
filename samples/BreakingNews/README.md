# Breaking news

A news site whose traffic arrives in spikes: a story breaks and a hundred thousand readers open it within minutes.
Every one of those requests used to insert a row into the views table, and the table is the one thing that cannot be
scaled by adding web servers. The newsroom still wants to see which stories are drawing readers today.

## The pieces

| File                                                                       | Role                                                       |
|----------------------------------------------------------------------------|------------------------------------------------------------|
| [`Story.php`](Story.php)                                                   | The viewable model                                         |
| [`ShowStory.php`](ShowStory.php)                                           | The controller that records a view with a cooldown         |
| [`FlushStoryViews.php`](FlushStoryViews.php)                               | Lands the buffer every minute and remembers when           |
| [`Newsroom.php`](Newsroom.php)                                             | Today's views per story, with how fresh they are           |
| [`NewsroomReport.php`](NewsroomReport.php)                                 | What the newsroom gets back                                |
| [`create_stories_table.php`](database/migrations/create_stories_table.php) | The `stories` table                                        |
| [`BreakingNewsTest.php`](BreakingNewsTest.php)                             | The behaviour below, as tests                              |

Switch the store to the `redis` driver in `config/eloquent-viewable.php`:

```php
'recording' => [
    'store' => [
        'driver' => 'redis',
    ],
],
```

Register the route and schedule the flush, for example in `AppServiceProvider::boot()` and `routes/console.php`:

```php
Route::get('/stories/{story}', ShowStory::class);

Schedule::call(app(FlushStoryViews::class))->everyMinute()->withoutOverlapping();
```

Read the dashboard:

```php
$report = app(Newsroom::class)->today();

$report->views;  // ['Dam breaks upstream' => 14_203, 'Council vote postponed' => 311]
$report->asOf;   // the last flush, or null before the first one
```

## Decisions

**Buffer in Redis, not in the queue.** Both move the insert out of the request, but a queued view is one job and one
insert per view, so the database still receives a row per reader, only later. The `redis` store appends every view to
a stream with one `XADD`, and the flusher lands them a thousand rows per insert statement. The request does one Redis
command instead of one database write, and the table sees a thousand times fewer statements during the spike. The
README's [benchmarks](../../benchmarks) put the two side by side.

**Keep the guards in the request.** The cooldown, the crawler check and the rest run before the view reaches the
store, whichever store it is. A reader who refreshes a developing story within the quarter hour never produces an
`XADD`, and a crawler never does once `IgnoreCrawlers` is listed. The buffer only receives views that would have been
rows.

**Flush on a schedule, through `Flusher`.** The package ships `views:flush`, and scheduling that command every minute
is all a plain installation needs. This sample wraps `Recording\Buffering\Flusher` in its own class for one reason:
the newsroom wants to know how old its numbers are, and the flusher is the one place that knows when the last batch
landed. The class drains the buffer and writes the time to the cache. `withoutOverlapping()` is a courtesy, not a
requirement: the consumer group behind the store hands each view to one flusher only, so two overlapping runs would
share the work rather than double it.

**Say what the numbers are.** Counts read the views table, so a view counts once it has landed, and a story that broke
thirty seconds ago shows the views of the previous flush. Rather than hide that, `NewsroomReport` carries the time of
the last flush as `asOf`, and the dashboard prints it next to the numbers. Before the first flush it is `null`, and the
counts are zero.

**Taking a story down takes its buffered views with it.** `Story::delete()` runs the package's observer, and
`forget()` on the `redis` store removes the story's views from the stream as well as from the table. The views of
other stories in the same buffer land as usual at the next flush.

## Where to take it next

- Show the top stories of the hour from `$report->views`, sorted, and print `asOf` as "updated a minute ago".
- Cache the report with `remember()` for a minute, the same interval as the flush, so the dashboard's queries do not
  grow with the number of editors watching it. The cached count then lags by two minutes at most.
- Keep an eye on the stream. If the scheduler stops, the buffer grows in Redis memory; the
  [README](../../README.md#buffering-views-in-redis) explains which eviction policy and persistence settings keep
  views safe until the flusher is back.
