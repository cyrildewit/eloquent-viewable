<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ViewsSpiked implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    public function __construct(
        public string $type,
        public int|string $key,
        public Baseline $baseline,
    ) {}
}
