<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

use CyrildeWit\EloquentViewable\Querying\Ranking\ViewableLoader;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * It loads the recommended models and their reasons in one query per type.
 * A recommendation whose model is gone is left out, and so is a reason.
 *
 * @phpstan-import-type Scored from Scorer
 *
 * @internal
 */
final readonly class RecommendationLoader
{
    public function __construct(
        private ViewableLoader $loader,
    ) {}

    /** @param  list<Scored>  $scored */
    public function load(array $scored): Recommendations
    {
        $rows = [];

        foreach ($scored as $row) {
            $rows[] = ['type' => $row['type'], 'id' => $row['id']];

            foreach ($row['because'] as $reason) {
                $rows[] = $reason;
            }
        }

        $models = $this->loader->models($rows);
        $entries = new Collection;

        foreach ($scored as $row) {
            $viewable = $models[$row['type']][(string) $row['id']] ?? null;

            if (! $viewable instanceof Model) {
                continue;
            }

            $because = new EloquentCollection;

            foreach ($row['because'] as $reason) {
                $model = $models[$reason['type']][(string) $reason['id']] ?? null;

                if ($model instanceof Model) {
                    $because->push($model);
                }
            }

            $entries->push(new Recommendation($viewable, $row['score'], $entries->count() + 1, $because));
        }

        return new Recommendations($entries);
    }
}
