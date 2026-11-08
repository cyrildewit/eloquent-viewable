---
title: "Collections"
description: "Store different kinds of views of the same model in their own collection, and count them the same way."
sidebar:
  order: 5
---

Not every view of a model is the same kind of view. A post can be opened on its own page, shown as a teaser in the
sidebar, or read inside the newsletter. A collection is a name you record a view under, so you can count those kinds
apart while they stay views of the same post.

## Recording into a collection

Pass the name to `collection()` before `record()`:

```php
// On the post page
views($post)->record();

// In the sidebar teaser
views($post)->collection('sidebar')->record();

// When the newsletter's tracking pixel loads
views($post)->collection('newsletter')->record();
```

A view recorded without `collection()` has no collection: its `collection` column is `null`. A name is any string you
pick. There is nothing to register.

The middleware and the browser beacon take the collection as an argument:

```php
Route::get('/amp/posts/{post}', ShowAmpPost::class)
    ->middleware(RecordViews::using('post', collection: 'amp'));
```

```blade
@viewsBeacon($post, collection: 'amp')
```

## Counting a collection

Add the same `collection()` to any count:

```php
views($post)->collection('sidebar')->count();                                       // sidebar views
views($post)->collection('sidebar')->unique()->period(Period::pastDays(7))->count(); // unique, this week
```

:::caution
Without `collection()`, a count includes **every** collection:

```php
views($post)->count(); // page views + sidebar views + newsletter views
```

There is no shortcut for "only the views without a collection". If you need that number often, record those views
into a collection of their own too, such as `page`.
:::

To see every collection at once, use `countByCollection()`:

```php
views($post)->countByCollection();
// ['' => 1200, 'sidebar' => 340, 'newsletter' => 88]
```

The empty string `''` stands for the views without a collection. The list is sorted by count, most viewed first.

## Everything else follows the collection

`collection()` works with every part of the package that reads views, so you can keep a kind of view separate from
start to finish:

| What                                               | Example                                                       |
|----------------------------------------------------|---------------------------------------------------------------|
| [Ordering](/eloquent-viewable/counting/ordering-and-filtering/) | `Post::orderByViews('desc', null, 'sidebar')->get()` |
| [Trending](/eloquent-viewable/rankings/trending/)  | `views(Post::class)->collection('sidebar')->trending()`       |
| [Also viewed](/eloquent-viewable/rankings/also-viewed/) | `views($post)->collection('sidebar')->alsoViewed(5)`     |
| [Recommended](/eloquent-viewable/rankings/recommended/) | `$user->recommended(Post::class, collection: 'articles')` |
| [Live presence](/eloquent-viewable/rankings/presence/)  | `views($product)->collection('checkout')->live()->count()` |
| [Tests](/eloquent-viewable/testing/)               | `$fake->assertRecorded($post, fn ($record) => $record->collection === 'sidebar')` |

A [cooldown](/eloquent-viewable/recording/cooldowns/) is kept per collection as well. A reader who opens the post
page and then sees its teaser in the sidebar is recorded once in each.

## When to use a collection

Use a collection when the same model is seen in clearly different places and you want separate numbers for each. Keep
the number of names small and fixed, like `sidebar`, `newsletter` or `amp`.

For a detail you only look up now and then, such as the campaign that brought the reader in, use
[context](/eloquent-viewable/recording/context/). For a value you want to chart or rank by across all views, such as
the device or the country, use a [dimension](/eloquent-viewable/viewers/dimensions/).

With [rollups](/eloquent-viewable/scaling/rollups/), counts of one model in a collection are summarised by the
`viewable_collection` grouping, which is on by default. Counts of a whole model type in a collection need
`type_collection`, which is off.
