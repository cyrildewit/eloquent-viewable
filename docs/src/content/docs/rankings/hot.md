---
title: "Hot"
description: "Give new content a head start with a hot ranking, the kind Hacker News and Reddit show."
sidebar:
  order: 4
---

[`trending()`](/eloquent-viewable/rankings/trending/) lets old views fade. A hot ranking, the kind Hacker News and Reddit show, gives *new content* a head
start instead: a post from this morning outranks one from last week with more views. The score is worked out once per
recount and kept in a [counter column](/eloquent-viewable/scaling/counts-table/), so ordering by it is an ordinary indexed
sort:

```php
'querying' => [
    'counters' => [
        Post::class => [
            'views_count',
            'hot_score' => ['hot' => true],
        ],
    ],
],
```

```php
Post::where('published', true)->orderByHot()->paginate(20);
```

Add the column in a migration of your own, as a `double` that defaults to `0`, with an index. The score is the
logarithm of the views plus a term that grows with the model's age, Reddit style: a post 12 hours newer needs a tenth
of the views to score the same. Tune both:

```php
'hot_score' => ['hot' => ['from' => 'published_at', 'every' => '1d']],  // a slower front page
'hot_score' => ['hot' => 'published_at', 'unique' => true],             // visitors instead of views
```

`from` names the timestamp column, `created_at` by default; a model where it's `null` scores on its views alone, below
every model with one. The other options of the column, `unique`, `period` and `collection`, say which views count.
Name the column, `orderByHot('hot_score')`, when a model has more than one. The score is as fresh as the last recount,
and a recount after you change `published_at` updates it.
