<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support\Concerns;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * An event that names a model by its morph type and key, so a queued listener
 * loads it under its own rules and one deleted since is null.
 *
 * @property-read string $type
 * @property-read int|string $key
 */
trait NamesViewable
{
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
