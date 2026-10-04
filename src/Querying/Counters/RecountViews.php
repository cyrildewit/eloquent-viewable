<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters;

use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Support\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * Writes the count of every configured counter column into the viewable's
 * own table, so ordering and filtering by it is a plain column read. Each
 * column is set from the source's correlated count, so it reads the rollups
 * as well as the views table under the `rollup` source.
 */
final readonly class RecountViews
{
    public function __construct(
        private ViewSource $source,
        private Config $config,
    ) {}

    /**
     * @return array<class-string, int> the models recounted, by class
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function handle(int $chunk): array
    {
        $recounted = [];

        foreach ($this->config->counters() as $class => $columns) {
            $model = new $class;
            $source = $this->source;
            $values = [];

            if (! $source instanceof SubquerySource) {
                throw UnsupportedBySource::counters($source);
            }

            foreach ($columns as $column => $query) {
                $values[$column] = $source->countSubquery($model, $query);
            }

            $key = $model->getQualifiedKeyName();
            $last = null;
            $recounted[$class] = 0;

            // Trashed models too, so a restored one comes back counted. Paged
            // by key, so a large table is written a chunk at a time.
            do {
                $keys = $this->table($model)
                    ->when($last !== null, fn (Builder $query): Builder => $query->where($key, '>', $last))
                    ->orderBy($key)
                    ->limit($chunk)
                    ->pluck($model->getKeyName())
                    ->all();

                if ($keys !== []) {
                    $this->table($model)->whereIn($key, $keys)->update($values);

                    // MySQL reports only the rows whose value changed, so the
                    // models are counted rather than the rows it reports.
                    $recounted[$class] += count($keys);
                    $last = end($keys);
                }
            } while (count($keys) === $chunk);
        }

        return $recounted;
    }

    /**
     * The model's table without its scopes, so trashed models are counted.
     */
    private function table(Model $model): Builder
    {
        return $model->getConnection()->table($model->getTable());
    }
}
