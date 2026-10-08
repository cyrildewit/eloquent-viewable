<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once when a model's views lie far below the same window on
 * past days or weeks. It does not fire again until the model has settled.
 */
class ViewsDropped implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    /**
     * @param  string  $type  the morph type of the model
     * @param  int|string  $key  the key of the model
     * @param  Baseline  $baseline  the count of the window and those it was compared with
     */
    public function __construct(
        public string $type,
        public int|string $key,
        public Baseline $baseline,
    ) {}
}
