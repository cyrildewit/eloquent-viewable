<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Events;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * It is dispatched once a counter column of a model crosses one of its
 * thresholds, after the mark that keeps it from firing again is committed.
 * A model that passed several thresholds since the last recount gets one
 * event, for the highest. It names the model by its morph type and key, so a
 * queued listener loads it under its own rules, and one deleted since is null.
 */
class ViewMilestoneReached implements ShouldDispatchAfterCommit
{
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

    /**
     * Load the model through its own query, so a trashed model, or one its
     * global scopes hide, is null.
     */
    public function viewable(): (Model&Viewable)|null
    {
        $class = Relation::getMorphedModel($this->type) ?? $this->type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $model = $class::query()->find($this->key);

        if (! $model instanceof Viewable) {
            return null;
        }

        return $model;
    }

    public function is(Model $model): bool
    {
        if ($model->getMorphClass() !== $this->type) {
            return false;
        }

        return (string) $model->getKey() === (string) $this->key; // @phpstan-ignore cast.string (a model key)
    }
}
