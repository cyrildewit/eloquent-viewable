---
title: "Macros"
description: "Add your own methods to the Views class with macros."
sidebar:
  order: 11
---

```php
use CyrildeWit\EloquentViewable\Views;

Views::macro('countByDay', function () {
    return $this->countByInterval(Granularity::Day);
});

views($post)->period(Period::pastDays(30))->countByDay();
```
