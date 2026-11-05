<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Events;

use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ViewMilestoneReached implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    /** @param  non-empty-list<int>  $passed */
    public function __construct(
        public string $type,
        public int|string $key,
        public string $column,
        public int $milestone,
        public int $count,
        public array $passed,
    ) {}
}
