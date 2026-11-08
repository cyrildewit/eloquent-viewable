---
title: "Macros"
description: "Add your own methods to the Views class with Laravel macros, such as a shortcut for a count or a ranking your application uses in many places."
sidebar:
  order: 11
---

```php
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Views;

Views::macro('countByDay', function () {
    return $this->countByInterval(Granularity::Day);
});

views($post)->period(Period::pastDays(30))->countByDay();
```
