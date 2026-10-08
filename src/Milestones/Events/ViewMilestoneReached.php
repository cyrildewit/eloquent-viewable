<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Events;

use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once a counter column of a model crosses one of its
 * thresholds, after the mark that keeps it from firing again is committed.
 * A model that passed several thresholds since the last recount gets one
 * event, for the highest. It names the model by its morph type and key, so a
 * queued listener loads it under its own rules, and one deleted since is null.
 */
class ViewMilestoneReached implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    /**
     * @param  string  $type  the morph type of the model
     * @param  int|string  $key  the key of the model
     * @param  string  $column  the counter column that crossed
     * @param  int  $milestone  the highest threshold it crossed
     * @param  int  $count  the count in the column now
     * @param  non-empty-list<int>  $passed  every threshold it crossed, in ascending order
     */
    public function __construct(
        public string $type,
        public int|string $key,
        public string $column,
        public int $milestone,
        public int $count,
        public array $passed,
    ) {}
}
