<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final readonly class ViewableLoader
{
    /** @param  list<array{type: string, id: int|string, count: int, score?: float, baseline?: Baseline}>  $rows */
    public function load(array $rows): Ranking
    {
        $models = $this->models($rows);
        $entries = new Collection;

        foreach ($rows as $row) {
            $viewable = $models[$row['type']][(string) $row['id']] ?? null;

            if ($viewable instanceof Model) {
                $entries->push(new Entry($viewable, $row['count'], $entries->count() + 1, $row['score'] ?? null, $row['baseline'] ?? null));
            }
        }

        return new Ranking($entries);
    }

    /**
     * Load the models the rows name, one query per type, keyed by morph type
     * and then by key. A row whose model is gone or not viewable is left out.
     *
     * @param  list<array{type: string, id: int|string}>  $rows
     * @return array<string, array<string, Model&Viewable>>
     */
    public function models(array $rows): array
    {
        $models = [];

        foreach (new Collection($rows)->groupBy('type') as $type => $group) {
            $instance = $this->instanceFor((string) $type);

            if (! $instance instanceof Model) {
                continue;
            }

            $query = $instance->newQuery();

            foreach ($query->whereKey($group->pluck('id')->unique()->values()->all())->get() as $model) {
                if ($model instanceof Viewable) {
                    $models[(string) $type][(string) ViewableKey::of($model)] = $model;
                }
            }
        }

        return $models;
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
