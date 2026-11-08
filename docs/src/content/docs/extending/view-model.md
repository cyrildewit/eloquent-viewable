---
title: "Your own View model"
description: "Extend the shipped model and name your class in the config."
sidebar:
  order: 2
---

Extend the shipped model and name your class in the config:

```php
// config/eloquent-viewable.php
'models' => [
    'view' => [
        'class' => \App\Models\View::class,
    ],
],
```

```php
namespace App\Models;

use CyrildeWit\EloquentViewable\Models\View as BaseView;

class View extends BaseView
{
    // ...
}
```

A class that does not extend the shipped model throws `InvalidConfiguration`. If you rename the table through the
model, set `table_name` to match, because the migration reads the config.
