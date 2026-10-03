<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final readonly class ViewableLoader
{
    /** @param  list<array{type: string, id: int|string, count: int}>  $rows */
    public function load(array $rows): Ranking
    {
        $models = [];

        foreach (new Collection($rows)->groupBy('type') as $type => $group) {
            $instance = $this->instanceFor((string) $type);

            if (! $instance instanceof Model) {
                continue;
            }

            $query = $instance->newQuery();

            foreach ($query->whereKey($group->pluck('id')->all())->get() as $model) {
                // Keyed as strings, so an integer from the views table matches
                // a string key and the other way round.
                if ($model instanceof Viewable) {
                    $models[(string) $type][(string) ViewableKey::of($model)] = $model;
                }
            }
        }

        $entries = new Collection;

        foreach ($rows as $row) {
            $viewable = $models[$row['type']][(string) $row['id']] ?? null;

            if ($viewable instanceof Model) {
                $entries->push(new Entry($viewable, $row['count'], $entries->count() + 1));
            }
        }

        return new Ranking($entries);
    }

    private function instanceFor(string $type): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class) || ! is_a($class, Model::class, true) || ! is_a($class, Viewable::class, true)) {
            return null;
        }

        return new $class;
    }
}
