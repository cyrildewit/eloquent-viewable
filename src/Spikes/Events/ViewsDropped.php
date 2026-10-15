<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once when a model's views lie far below the same window on
 * past days or weeks, with the baseline they were compared with. It does not
 * fire again until the model has settled.
 */
class ViewsDropped implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    public function __construct(
        public string $type,
        public int|string $key,
        public Baseline $baseline,
    ) {}
}
