<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Events;

use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once a counter column of a model crosses one of its
 * thresholds, after the mark that keeps it from firing again is committed.
 * A model that passed several thresholds since the last recount gets one
 * event, for the highest, with every threshold it passed in ascending order
 * and the count the column holds now. It names the model by its morph type
 * and key, so a queued listener loads it under its own rules, and one deleted
 * since is null.
 */
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
