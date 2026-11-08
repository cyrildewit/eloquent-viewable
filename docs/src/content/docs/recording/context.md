---
title: "Storing context"
description: "Keep anything else with a view, such as a referrer, a source or a tenant, in the nullable context JSON column."
sidebar:
  order: 6
---

Every view stores which model was viewed, who viewed it and when. Sometimes you want to know a bit more: which
newsletter brought the reader in, which A/B variant they saw, or which tenant the page belonged to. The nullable
`context` JSON column holds that. The package writes whatever you give it and never reads it back, so its shape is up to
you.

## Recording context

Say you link to a post from your newsletter as `/posts/my-post?utm_source=newsletter&utm_campaign=october`. Pass what
you want to keep to `context()` before `record()`:

```php
use App\Models\Post;

class ShowPost
{
    public function __invoke(Post $post)
    {
        views($post)
            ->context([
                'source' => request('utm_source'),
                'campaign' => request('utm_campaign'),
                'variant' => session('headline_variant'),
            ])
            ->record();

        return view('posts.show', ['post' => $post]);
    }
}
```

The array is stored as JSON, so it can hold strings, numbers, booleans and nested arrays. A view recorded without
`context()` stores `null`.

:::note
The [`RecordViews` middleware](/eloquent-viewable/recording/recording-views/#recording-from-a-route) has no way to pass context. Record in
the controller, as above, when a view needs it.
:::

The context travels with the view wherever it goes before landing in the table: a
[queued view](/eloquent-viewable/recording/queueing/) keeps the context captured during the request, and so does a view
held in the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/).

## Reading it back

`$post->views()` is an ordinary Eloquent relation, so Laravel's JSON `->` syntax works on it:

```php
// Views that came from the newsletter
$post->views()->where('context->source', 'newsletter')->count();

// Views from the October campaign, this week
$post->views()
    ->where('context->campaign', 'october')
    ->where('viewed_at', '>=', now()->subWeek())
    ->count();
```

The `View` model casts the column to an array, so on a loaded view you read it like any other array:

```php
$view = $post->views()->latest('viewed_at')->first();

$view->context['source'] ?? 'direct'; // 'newsletter'
```

For a quick breakdown of a handful of views, group them in PHP:

```php
$post->views()
    ->where('viewed_at', '>=', now()->subDay())
    ->get(['context'])
    ->countBy(fn ($view) => $view->context['source'] ?? 'direct');
// ['newsletter' => 42, 'direct' => 17, 'twitter' => 5]
```

This loads every row, so keep it to small sets.

## Context or a dimension?

A JSON path cannot use the table's indexes, and the counting API (`count()`, `countByInterval()`, the rankings) does
not look inside `context`. When you want to count views by a value, define a
[dimension](/eloquent-viewable/viewers/dimensions/) instead.

|                                             | Context                       | Dimension                                   |
|---------------------------------------------|-------------------------------|---------------------------------------------|
| Set up                                      | Nothing, call `context()`     | A class and a config entry                  |
| Set by                                      | You, on each recording        | The package, from the request, on every view |
| Filter with `views($post)->...->count()`    | No, only with `$post->views()` | Yes                                         |
| Uses an index                               | No                            | Yes, in a column of its own                 |
| Kept in [rollups](/eloquent-viewable/scaling/rollups/) | No                 | Yes                                         |
| Survives anonymising                        | No                            | Yes, unless marked personal                 |

A good rule: use context for details you look up now and then, and a dimension for anything you chart or rank by.

A dimension with the `json` option is stored in `context` too, under its own key, next to whatever you pass. Pick keys
that don't clash with it.

## Privacy and retention

Context is treated as personal data, because the package can't know what you put in it:

- A [retention policy](/eloquent-viewable/scaling/retention/) clears it when it anonymises old views, as long as
  `retention.anonymise.columns` includes `context`, which it does by default.
- `Erasure\Actions\ExportViewHistory` includes it when a viewer
  [asks for their data](/eloquent-viewable/viewers/erasing-views/).

So leave out what you would not want to export or keep, such as an IP address or an email address. If you need a
value to outlive anonymising, make it a dimension.

## Testing

With [`Views::fake()`](/eloquent-viewable/testing/), every recorded view is kept in memory with its context, so you can
assert on it:

```php
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Facades\Views;

Views::fake();

$this->get('/posts/my-post?utm_source=newsletter');

Views::assertRecorded($post, fn (ViewRecord $view) => $view->context['source'] === 'newsletter');
```

While developing, the [Debugbar tab](/eloquent-viewable/recording/recording-views/#seeing-it-in-debugbar-and-telescope) shows the context of each view the
request recorded.
