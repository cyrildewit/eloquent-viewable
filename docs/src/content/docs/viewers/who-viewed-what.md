---
title: "Who viewed what"
description: "A view can be linked to the signed-in model through the polymorphic viewer_type and viewer_id columns, so any model can be a viewer."
sidebar:
  order: 1
---

A view can be linked to the signed-in model through the polymorphic `viewer_type` and `viewer_id` columns, so any model
can be a viewer. Guests leave them `null`.

## Recording the viewer

Recording the viewer is off by default, because it ties a view to an identity. Turn it on in the config; `guard` names
the auth guard, and `null` means the default one:

```php
'recording' => [
    'viewer' => [
        'enabled' => true,
        'guard' => null,
    ],
],
```

To credit a view to a model yourself, for example in a console command, use `viewedBy()`. It works whether or not the
switch is on. A queued view keeps its viewer.

```php
views($post)->viewedBy($user)->record();
```

## Counting the views of one viewer

```php
views($post)->viewedBy($user)->count();
views(Post::class)->viewedBy($user)->period(Period::pastDays(7))->count(); // every post
```

## Counting one account as one visitor

By default `unique()` counts browsers, so a user on three devices counts three times. Set `visitor.identity` to `viewer`
to count signed-in users by their account instead. Guests keep the cookie id.

```php
'visitor' => [
    'identity' => 'viewer',
],
```

The id is an HMAC of the model with `app.key`, so rotating the application key splits the unique counts of signed-in
users at that moment. A guest who signs in counts as two visitors.

## Which models a viewer has seen

```php
Post::whereViewedBy($user)->get();
Post::whereNotViewedBy($user)->get();                       // the unread ones
Post::whereViewedBy($user, Period::pastDays(7))->get();
Post::whereNotViewedBy($user, collection: 'sidebar')->get();

Post::whereNotViewedByVisitor($visitorId)->get();          // for guests, by visitor id
```

Views that [retention](/eloquent-viewable/scaling/retention/) has anonymised no longer belong to a viewer, so `whereNotViewedBy()` lists those
models as unread again. See [the viewer side](/eloquent-viewable/viewers/who-viewed-what/#the-viewer-side) for how to keep the history.

## The viewer side

Add the optional `HasViewHistory` trait to the model that views things:

```php
use CyrildeWit\EloquentViewable\Concerns\HasViewHistory;

class User extends Authenticatable
{
    use HasViewHistory;
}

$user->viewed()->with('viewable')->paginate();   // what they looked at, newest first
$user->hasViewed($post, Period::pastDays(7));    // bool
$user->lastViewedAt($post);                      // Carbon or null
```

Each `View` also has a `viewer` relation and `byViewer()` and `byVisitor()` scopes.

Good to know: **anonymising clears reading history.** `retention.anonymise.columns` includes `viewer` by default, so
once views pass `retention.anonymise.after`, `viewed()`, `hasViewed()` and `lastViewedAt()` forget them,
`whereNotViewedBy()` lists their models as unread again and `recommended()` has less history to start from. For a
"continue where you left off" feature, leave `viewer` out of `anonymise.columns`, or set `anonymise.after` to the
history the product needs.

## Letting a viewer opt out

For a "don't record my reading history" account setting, implement `Contracts\ViewerCanOptOut` on the viewer model.
While `tracksViews()` returns `false`, the `IgnoreOptedOutViewers` guard drops their views:

```php
use CyrildeWit\EloquentViewable\Contracts\ViewerCanOptOut;

class User extends Authenticatable implements ViewerCanOptOut
{
    public function tracksViews(): bool
    {
        return ! $this->settings->hide_reading_history;
    }
}
```

The guard is on by default and does nothing for guests or models without the contract. It asks the model passed to
`viewedBy()`, otherwise the user signed in on `recording.viewer.guard`, also when `recording.viewer.enabled` is off,
because the visitor cookie still ties their views together. A dropped view starts no cooldown and does not keep them
[looking right now](/eloquent-viewable/rankings/presence/). Views recorded before they opted out are kept; call
[`forgetViewHistory()` or `anonymiseViewHistory()`](/eloquent-viewable/viewers/erasing-views/) to remove them.
