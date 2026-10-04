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
 * Each column is set from the source's correlated count, so under the `rollup`
 * source it includes history whose views are gone.
 */
final readonly class RecountViews
{
    public function __construct(
        private ViewSource $source,
        private Config $config,
    ) {}

    /**
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
     * MySQL reports only the rows whose value changed, so the models are
     * counted instead.
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
     * The table is read without the model's scopes, so trashed models are
     * counted too.
     */
    private function table(Model $model): Builder
    {
        return $model->getConnection()->table($model->getTable());
    }
}
