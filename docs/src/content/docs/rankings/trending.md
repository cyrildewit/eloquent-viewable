---
title: "Trending"
description: "Rank what is getting views now, with older views counting less the older they get."
sidebar:
  order: 2
---

[`top()`](/eloquent-viewable/rankings/most-viewed/) ranks by how many views something got in a period, and every view in that period counts the same.
`trending()` ranks by how many views something is getting *now*: a recent view counts fully and an older one counts
less the older it gets. Something that's taking off this hour ranks above something that was busy last week.

```php
$trending = views(Post::class)->trending(10);

foreach ($trending as $entry) {
    $entry->rank;      // 1, 2, 3, ...
    $entry->viewable;  // the post
    $entry->count;     // its views in the window, for a "1,234 views" label
    $entry->score;     // its views weighed by age, see below
}

Views::trending(10);   // across every model type
```

## How the ranking is made

Every view gets a weight between 1 and 0, depending on its age, and a model's score is the sum of those weights. By
default a view loses half its weight every day. A view from now counts as 1, one from yesterday as 0.5, and one from a
week ago as less than 0.01.

Say three posts got these views:

| Post          | Views              | `top()` over 7 days | `trending()` score |
|---------------|--------------------|---------------------|--------------------|
| Queues guide  | 1,000, 6 days ago  | 1st, 1,000          | 3rd, about 16      |
| Release notes | 800, the past hour | 2nd, 800            | 1st, about 800     |
| Redis tips    | 400, yesterday     | 3rd, 400            | 2nd, about 200     |

`top()` still puts the Queues guide first, a week after everyone stopped reading it. `trending()` puts the release
notes first, the moment they take off, and lets the Queues guide fade out gradually instead of dropping off at the end
of the window.

Read the score as "worth this many views right now". Use it to sort, chart or set a threshold, such as showing a badge
above 50, but don't show it as a view count. That's what `count` is for.

## Choosing how fast views fade

The half-life is how long a view takes to lose half its weight. A short one reacts fast and forgets fast. A long one
favours steady traffic. Set it once in the config:

```php
'querying' => [
    'trending' => [
        'half_life' => '1d', // news, social: what's hot today
        // 'half_life' => '1w', // a shop or catalogue: what's in demand lately
    ],
],
```

or for one call with `trending(halfLife: CarbonInterval::hours(6))`.

The half-life is a setting of exponential decay, the default curve. Two other curves ship with the package:

| A view of this age weighs  | now | 1 day | 3 days | 6 days | 7 days |
|----------------------------|-----|-------|--------|--------|--------|
| `ExponentialDecay`, 1 day  | 1   | 0.5   | 0.13   | 0.02   | 0.01   |
| `ExponentialDecay`, 1 week | 1   | 0.91  | 0.74   | 0.55   | 0.5    |
| `LinearDecay`, 7 days      | 1   | 0.86  | 0.57   | 0.14   | 0      |
| `Window`, 7 days           | 1   | 1     | 1      | 1      | 0      |

- **`ExponentialDecay`**, the default, suits most lists. It never drops anything abruptly.
- **`LinearDecay`** fades evenly to zero at the end of its window, for "this week" lists that should forget the
  previous week completely.
- **`Window`** counts every view in the window the same. That's `top()`, but with a `score`, for when one list should
  switch between the two.

```php
use CyrildeWit\EloquentViewable\Querying\Ranking\Curves\LinearDecay;

views(Post::class)->trending(curve: new LinearDecay(CarbonInterval::days(7)));
```

To use another curve everywhere, set `querying.trending.curve` to its class. To write your own, see
[Writing a trending curve](/eloquent-viewable/extending/trending-curves/).

## Ordering models by trending

To paginate, filter or eager load, use the scopes:

```php
Post::where('published', true)->orderByTrending()->paginate(20);
Post::withTrendingScore()->get();                       // adds `trending_score`
Post::orderByTrending(halfLife: CarbonInterval::days(7), collection: 'amp')->get();
```

They take the same `period`, `collection`, `unique` and `as` arguments as `orderByViews()`, plus `halfLife` and
`curve`. A post without recent views scores 0.

## Narrowing it down

```php
views(Post::class)->period(Period::pastDays(3))->trending();    // only views of the past 3 days
views(Post::class)->period(Period::create('2026-09-01', '2026-10-01'))->trending(); // trending in September
views(Post::class)->collection('amp')->trending();
views(Post::class)->unique()->trending();                       // visitors instead of views
```

Without a period, views count until they weigh almost nothing: eight half-lives, so eight days by default. The end of
the period is the "now" that ages are measured from.

## Good to know

- **Give `remember()` a short lifetime.** A remembered ranking does not change until it expires. Ten minutes is plenty
  for a sidebar: `remember(10)`.
- **The ranking moves once an hour.** Views are weighed per hour, or per day for a window too long for hours. A view
  from 10:05 and one from 10:55 weigh the same.
- **`unique()` counts a visitor once per hour**, or once per day when views are weighed per day. A reader who comes
  back the next day counts again, which is what makes something trending.
- **Scores compare only under the same curve.** A one-day and a one-week half-life give different scores for the same
  views.

## On large tables

`trending()` reads the same rows as `top()` over the same window, so the `(viewable_type, viewable_id, viewed_at)`
index from the migration covers it. With the [`rollup` source](/eloquent-viewable/scaling/rollups/), the hourly tier is used when you keep one. A
day tier alone can't tell hours apart, so with hourly weighing those reads come from the `views` table. Keep an `hour`
tier for as long as your trending window, or set `querying.trending.step` to `'1d'`.
