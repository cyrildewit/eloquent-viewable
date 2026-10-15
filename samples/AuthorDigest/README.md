# Author digest

A blogging platform mails its authors on Monday morning: "Your essays got 4,210 views in the week of 5 October, up
12% on the week before", followed by their three most viewed essays. Authors live all over the world, so Monday
morning and the week it closes are theirs, not the server's.

## The pieces

| File                                                                                             | Role                                              |
|--------------------------------------------------------------------------------------------------|---------------------------------------------------|
| [`Author.php`](Author.php)                                                                       | The author, notifiable, with a timezone           |
| [`Essay.php`](Essay.php)                                                                         | The viewable model                                |
| [`AuthorDigests.php`](AuthorDigests.php)                                                         | Counts one author's week                          |
| [`WeeklyDigest.php`](WeeklyDigest.php)                                                           | The numbers of that week                          |
| [`ViewsDigest.php`](ViewsDigest.php)                                                             | The queued notification and its mail              |
| [`SendWeeklyDigests.php`](SendWeeklyDigests.php)                                                 | Finds who is due and sends their digest           |
| [`create_authors_and_essays_tables.php`](database/migrations/create_authors_and_essays_tables.php) | The `authors` and `essays` tables                 |
| [`AuthorDigestTest.php`](AuthorDigestTest.php)                                                   | The behaviour below, as tests                     |

Views are recorded on the essay pages the usual way; see the [trending articles](../TrendingArticles) sample for a
controller that does so with a cooldown. Schedule the digests every hour, for example in `routes/console.php`:

```php
Schedule::call(app(SendWeeklyDigests::class))->hourly()->withoutOverlapping();
```

The mail reads:

```text
Hi Ada,

Your essays got 4,210 views in the week of 5 October, up 12% on the week before.

1. On indexes: 3,100 views
2. On naming: 802 views
3. On caching: 61 views
```

## Decisions

**Run every hour, send on the author's Monday.** A weekly schedule at Monday 08:00 UTC would mail an author in Los
Angeles at one in the morning about a week that, on their clock, ended an hour before, and one in Tokyo half a day
late. `SendWeeklyDigests` runs hourly and, per timezone the authors use, works out the last week that has ended there
and whether it is 08:00 on Monday yet. Before that, the last whole week is the one already sent last Monday, so nobody
is due.

**Remember the week, not the run.** `digested_week` holds the Monday of the last week an author was sent, as a date on
their clock. The query selects authors whose column is behind the week that is due, so the run that comes an hour
later finds nobody, and one that was missed because the scheduler was down catches up at the next. An author whose
essays had no views that week gets no mail but is marked too, so they are not counted again every hour until next
Monday.

**Count calendar weeks.** `compare()` on a period of two dates steps back by its exact width. In the week Europe goes
back to winter time that width is 169 hours, so the week before would start at 23:00 on the Sunday before it and take
an hour of the week before that. `AuthorDigests` builds both weeks from midnight on Monday, on the author's clock, and
passes them to `ViewComparison::between()`, so the percentage has the same rules as `compare()`: rounded to one
decimal, and `null` when the week before had no views.

**Count an author's essays in one query per week.** `compare()` and `top()` count a single model or a whole type, not
the essays of one author. `forViewables($essays)->counts()` counts a set in one grouped query and returns zero for an
essay without views, so the week is two queries whatever the number of essays: one for the total, sorted in PHP for the
top three, and one for the week before. A ranking over the whole `Essay` type would put other authors' essays in the
list.

**Do not remember the counts.** Each week is counted once per author and never read again, so `remember()` would only
fill the cache. The numbers travel in the notification instead.

**Queue numbers, not models.** `WeeklyDigest` holds titles and counts, so the queued `ViewsDigest` sends what was
counted. An essay retitled or deleted before the queue gets to it does not change the mail or fail the job, and the
worker runs no queries of its own.

## Where to take it next

- Count readers instead of views with `unique()` on the builder in `AuthorDigests::counts()`, which `counts()`
  honours, and say "read by 1,850 people".
- Add a Slack message next to the mail with
  [`laravel/slack-notification-channel`](https://github.com/laravel/slack-notification-channel): another entry in
  `via()` and a `toSlack()` built from the same `WeeklyDigest`.
- Spread a large run over the queue by dispatching a job per author from `SendWeeklyDigests` and counting in the job.
  The run then only selects who is due, and the counting happens on the workers.
- An author with thousands of essays puts every key in the `IN` list of `counts()`. Past a few thousand, count their
  essays in chunks and add the counts up.
