<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Http\Concerns;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

trait FindsViewables
{
    /**
     * The type is the morph class the signed URL names, so a model is only
     * found when the URL was built for it.
     */
    protected function find(string $type, string $key): ?Viewable
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class)) {
            return null;
        }

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $model = $class::query()->whereKey($key)->first();

        if (! $model instanceof Viewable) {
            return null;
        }

        return $model;
    }
}
