---
title: "Milestones"
description: "Notify an author when their post passes 1,000 views, award a badge, or post to Slack."
sidebar:
  order: 5
---

Notify an author when their post passes 1,000 views, award a badge, or post to Slack. Milestones are thresholds on a
[counter column](/eloquent-viewable/scaling/counts-table/), so list the column under `querying.counters` first, then its
thresholds in ascending order:

```php
'querying' => [
    'counters' => [
        Post::class => ['views_count', 'unique_views_count' => ['unique' => true]],
    ],
],

'milestones' => [
    'thresholds' => [
        Post::class => [
            'views_count' => [100, 1_000, 10_000, 100_000],
            'unique_views_count' => [1_000],
        ],
    ],
],
```

Every recount, from `views:recount`, `views:maintain` or `destroy()`, compares the columns it writes with their
thresholds and dispatches `ViewMilestoneReached` once per model and threshold:

```php
use CyrildeWit\EloquentViewable\Milestones\Events\ViewMilestoneReached;

class NotifyAuthorOfMilestone implements ShouldQueue
{
    public function handle(ViewMilestoneReached $event): void
    {
        $post = $event->viewable(); // null once the post is deleted

        $post?->author->notify(new PostReachedMilestone($post, $event->milestone));
    }
}
```

The event carries the model's morph `type` and `key`, the `column`, the `milestone` it crossed, the `count` now, and
every threshold it `passed` since the last recount. A post that jumps from 50 to 12,000 views between two runs gets
one event for 10,000, with `[100, 1_000, 10_000]` in `passed`. `viewable()` loads the model through its own query, so
a trashed model, or one a global scope hides, is `null`. `is($post)` compares without loading it.

## Good to know

- **A milestone fires once.** The highest count a model crossed a threshold at is kept in the `view_milestones` table,
  so a count that drops, after an erasure or `views:purge-bots`, and climbs back crosses nothing twice.
- **Turning milestones on sends nothing for the past.** The first recount marks every model at its current count. A
  threshold you add later is covered the same way for the models already past it; the thresholds that were there
  before still fire. To mark every model by hand, after an import for instance, run `php artisan views:seed-milestones`,
  optionally with a model class.
- **Queue your listeners.** The marks are written first and the event is dispatched once they are committed, so a
  listener that throws loses that milestone rather than blocking every recount after it. A queued listener gets the
  queue's retries.
- **A column with a `period` cannot have milestones**, because a count over the past week goes up and down.
- **A `unique` column stops counting anonymised views**, so its count can drop once
  [retention](/eloquent-viewable/scaling/retention/) anonymises them. It never counts more visitors than there were.
- **Milestones are as fresh as the last recount.** Schedule `views:maintain` as often as you need them.
