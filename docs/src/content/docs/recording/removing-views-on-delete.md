---
title: "Removing views on delete"
description: "A model's views are deleted with it, unless you keep them or the model is soft deleted."
sidebar:
  order: 7
---

When you delete a viewable model, its views are deleted with it. You don't need to set anything up: the
`InteractsWithViews` trait registers an observer that listens for the model's `deleted` event.

```php
$post->delete(); // the post and all of its views are gone
```

Along with the rows in the `views` table, the package removes the post's rollups, forgets its cached counts and
drops any of its views still waiting in the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/). It then
dispatches a `Recording\Events\ViewsDestroyed` event, which you can [listen for](/eloquent-viewable/reference/events/).

## Soft deletes

If the model uses Laravel's `SoftDeletes` trait, `delete()` only sets `deleted_at`. The views stay, so a restored
model gets its counts back:

```php
$post->delete();      // soft delete, the views stay
$post->restore();     // the post is back, with its views
$post->forceDelete(); // the post and its views are gone for good
```

To remove the views of a soft-deleted model without deleting the model itself, call `destroy()`:

```php
views($post)->destroy();
```

## Keeping the views

To keep the views after the model is deleted, for example for reporting, add a `shouldRemoveViewsOnDelete()` method
to the model and return `false`:

```php
use CyrildeWit\EloquentViewable\Concerns\InteractsWithViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements Viewable
{
    use InteractsWithViews;

    public function shouldRemoveViewsOnDelete(): bool
    {
        return false;
    }
}
```

The method overrides the trait's default, which returns `true`. You can also decide per model, for example
`return ! $this->is_featured;`.

## Deleting many models at once

Laravel only fires model events when you delete a model instance. A delete that runs as a single query skips them,
so the views of those models are **not** removed:

```php
// The views stay behind
Post::where('created_at', '<', now()->subYear())->delete();
```

Load the models and delete them one by one instead. `lazyById()` keeps memory flat when there are many:

```php
// Each post's views are removed with it
Post::where('created_at', '<', now()->subYear())
    ->lazyById()
    ->each->delete();
```

The same applies to `Post::truncate()` and to a foreign key with `ON DELETE CASCADE`: neither fires model events.
