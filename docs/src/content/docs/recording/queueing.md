---
title: "Queueing"
description: "Move the insert to a queue worker to keep busy pages fast."
sidebar:
  order: 4
---

Move the insert to a queue worker to keep busy pages fast. Queue a single view, or every view from the config:

```php
views($post)->queue()->record();
views($post)->queue(false)->record(); // record synchronously when queueing is on globally
```

```php
'recording' => [
    'queue' => [
        'enabled' => true,
        'connection' => null,   // null uses the default queue connection
        'queue' => null,        // null uses the connection's default queue
    ],
],
```

The guards still run during the request, so bots and views on cooldown are never queued. Leave queueing off when you
use the [Redis buffer](/eloquent-viewable/scaling/redis-buffer/), which already moves the write out of the request.

:::caution
A queued view dispatches `ViewRecorded` from the worker, where the session, cookies, `request()` and `auth()` are
unavailable. Read what you need from `$event->record`, which carries the viewer and the
[context](/eloquent-viewable/recording/context/) captured during the request.
:::
