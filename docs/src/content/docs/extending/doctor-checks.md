---
title: "Doctor checks"
description: "doctor.checks lists the checks views:doctor runs, in order."
sidebar:
  order: 6
---

`doctor.checks` lists the checks [`views:doctor`](/eloquent-viewable/scaling/checking-your-setup/) runs, in order. Remove one to skip it, or add a
class of your own that implements `Doctor\Contracts\Check`. Checks are resolved from the container, and one that
throws is reported as a failure, so the others still run.

```php
use CyrildeWit\EloquentViewable\Doctor\Contracts\Check;
use CyrildeWit\EloquentViewable\Doctor\Data\Finding;
use Illuminate\Contracts\Queue\Factory as Queue;

class QueueBacklogCheck implements Check
{
    public function __construct(
        protected Queue $queue,
    ) {}

    public function name(): string
    {
        return 'Views queue';
    }

    public function run(): iterable
    {
        $waiting = $this->queue->connection('redis')->size('views');

        if ($waiting > 10_000) {
            yield Finding::warning("{$waiting} views wait on the queue.", 'Start another worker for the `views` queue.');

            return;
        }

        yield Finding::pass("{$waiting} views wait on the queue.");
    }
}
```

A finding is a `pass`, `advice`, `warning`, `failure` or `skipped`, with an optional fix. `--only` picks a check by its
class name in kebab case, without the `Check` suffix: `queue-backlog` for the one above.
