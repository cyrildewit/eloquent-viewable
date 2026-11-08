---
title: "Removing views on delete"
description: "A model's views are deleted with it."
sidebar:
  order: 7
---

A model's views are deleted with it. A soft delete keeps them until `forceDelete()`. To keep the views, override
`shouldRemoveViewsOnDelete()`:

```php
public function shouldRemoveViewsOnDelete(): bool
{
    return false;
}
```

To drop the views of a soft-deleted model anyway, call `views($post)->destroy()`.
