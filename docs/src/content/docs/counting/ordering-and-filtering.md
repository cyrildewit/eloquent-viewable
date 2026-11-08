---
title: "Ordering and filtering"
description: "Order and filter Eloquent queries by view count with orderByViews() and whereViewsCount()."
sidebar:
  order: 3
---

```php
Post::orderByViews()->get();                                        // most viewed first
Post::orderByViews('asc')->get();
Post::orderByUniqueViews('desc', Period::pastDays(3))->get();
Post::orderByViews('desc', null, 'custom-collection')->get();

Post::whereViewsCount('>=', 1000)->get();
Post::whereUniqueViewsCount('>=', 50, Period::pastDays(30))->get();
Post::whereViewsCount('>=', 100)->orderByViews()->get();
```

`whereViewsCount()` takes the same period and collection arguments, counts a model without views as zero, and accepts
`=`, `!=`, `<>`, `<`, `<=`, `>` and `>=`. Both scopes run a subquery per row and count every row of the query before
the database can sort or filter, even when you only take one page, so narrow the query where you can. On list pages,
sort and filter on a [counter column](/eloquent-viewable/scaling/counts-table/) instead, which reads one indexed column.
To only check whether a model has any views, `Post::has('views')` is cheaper.
