---
title: "Most viewed"
description: "Views::top() ranks the most viewed content across every model type."
sidebar:
  order: 1
---

`Views::top()` ranks the most viewed content across every model type:

```php
use CyrildeWit\EloquentViewable\Facades\Views;

$ranking = Views::period(Period::pastDays(7))->top(5);

foreach ($ranking as $entry) {
    $entry->rank;      // 1, 2, 3, ...
    $entry->count;     // the number of views
    $entry->viewable;  // a Post, a Video, ... whichever model it is
}

views(Post::class)->top(10);  // within one model
```

The models are loaded with one query per type and never cached, so a `remember()`ed ranking shows fresh attributes. A
model that can no longer be loaded, for example because it was deleted, is left out, so a ranking can hold fewer
entries than the limit. The ranking serializes to JSON as `rank`, `count` and `viewable`, and `score` for
[trending](/eloquent-viewable/rankings/trending/) rankings.
