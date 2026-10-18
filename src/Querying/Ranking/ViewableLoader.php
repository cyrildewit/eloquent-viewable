<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Growth\Baseline;
use CyrildeWit\EloquentViewable\Support\MorphLoader;
use Illuminate\Database\Eloquent\Model;
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

        foreach (MorphLoader::load($rows) as $type => $loaded) {
            foreach ($loaded as $key => $model) {
                if ($model instanceof Viewable) {
                    $models[$type][$key] = $model;
                }
            }
        }

        return $models;
    }
}
