---
title: "Storing context"
description: "Keep anything else with a view, such as a referrer, a source or a tenant, in the nullable context JSON column."
sidebar:
  order: 6
---

Keep anything else with a view, such as a referrer, a source or a tenant, in the nullable `context` JSON column. The
package writes it and never reads it. A queued view keeps it.

```php
views($post)->context(['source' => request('src')])->record();

$post->views()->where('context->source', 'newsletter')->count();
```

A JSON path cannot use the table's indexes. To count views by a value, such as the source or the device, use a
[dimension](/eloquent-viewable/viewers/dimensions/) instead: it is kept in a column of its own and outlives anonymising and pruning in the rollups.
