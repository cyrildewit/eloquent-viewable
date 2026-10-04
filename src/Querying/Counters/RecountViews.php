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
 * This action writes the count of every configured counter column into the
 * viewable's own table, so ordering and filtering by it is a plain column
 * read. Each column is set from the source's correlated count, so it reads the
 * rollups as well as the views table under the `rollup` source.
 */
final readonly class RecountViews
{
    public function __construct(
        private ViewSource $source,
        private Config $config,
    ) {}

    /**
     * It returns the number of models recounted, by class.
     *
     * @return array<class-string, int>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function handle(int $chunk): array
    {
        $source = $this->source;

        if (! $source instanceof SubquerySource) {
            throw UnsupportedBySource::counters($source);
        }

        $recounted = [];

        foreach ($this->config->counters() as $class => $columns) {
            $model = new $class;
            $values = [];

            foreach ($columns as $column => $query) {
                $values[$column] = $source->countSubquery($model, $query);
            }

            $recounted[$class] = $this->recount($model, $values, $chunk);
        }

        return $recounted;
    }

    /**
     * It writes the models a chunk at a time, paged by key, and counts the
     * models rather than the rows the update reports, because MySQL reports
     * only the rows whose value changed.
     *
     * @param  array<string, Builder>  $values
     */
    private function recount(Model $model, array $values, int $chunk): int
    {
        $key = $model->getQualifiedKeyName();
        $last = null;
        $recounted = 0;

        do {
            $keys = $this->table($model)
                ->when($last !== null, fn (Builder $query): Builder => $query->where($key, '>', $last))
                ->orderBy($key)
                ->limit($chunk)
                ->pluck($model->getKeyName())
                ->all();

            if ($keys === []) {
                break;
            }

            $this->table($model)->whereIn($key, $keys)->update($values);

            $recounted += count($keys);
            $last = end($keys);
        } while (count($keys) === $chunk);

        return $recounted;
    }

    /**
     * This is the model's table without its scopes, so trashed models are
     * counted too and a restored one comes back counted.
     */
    private function table(Model $model): Builder
    {
        return $model->getConnection()->table($model->getTable());
    }
}
