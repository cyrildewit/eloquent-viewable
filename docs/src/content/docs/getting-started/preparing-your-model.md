---
title: "Preparing your model"
description: "Make an Eloquent model viewable by implementing the Viewable interface and using the InteractsWithViews trait, so you can record and count its views."
sidebar:
  order: 3
---

Implement the `Viewable` interface and use the `InteractsWithViews` trait:

```php
use Illuminate\Database\Eloquent\Model;
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;

class Post extends Model implements Viewable
{
    use InteractsWithViews;
}
```
