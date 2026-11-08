---
title: "Counts of models you already have"
description: "For a page of results you already loaded, forViewables() counts them all in one query instead of one per model."
sidebar:
  order: 2
---

For a page of results you already loaded, `forViewables()` counts them all in one query instead of one per model:

```php
$posts = Post::query()->latest()->paginate(20);

$counts = Views::forViewables($posts)->period(Period::pastDays(7))->counts();

$counts[$post->getKey()]; // 0 for a post without views
```

It takes any collection, paginator or array of saved models of one type. With `remember()`, only the models missing
from the cache are counted.
