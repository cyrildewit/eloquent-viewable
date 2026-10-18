<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Loads the models that morph references name, one query per type. A
 * reference whose type is not a model, or whose model is gone, is left out.
 *
 * @internal
 */
final class MorphLoader
{
    /**
     * @param  list<array{type: string, id: int|string}>  $references
     * @return array<string, array<string, Model>> by morph type, then by key
     */
    public static function load(array $references): array
    {
        $models = [];

        foreach (new Collection($references)->groupBy('type') as $type => $group) {
            $class = Relation::getMorphedModel((string) $type) ?? (string) $type;

            if (! class_exists($class) || ! is_a($class, Model::class, true)) {
                continue;
            }

            foreach (new $class()->newQuery()->whereKey($group->pluck('id')->unique()->values()->all())->get() as $model) {
                $models[(string) $type][(string) ViewerKey::of($model)] = $model;
            }
        }

        return $models;
    }
}
