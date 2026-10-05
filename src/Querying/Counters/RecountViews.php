<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
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
 * source it includes history whose views are gone. `handle()` recounts every
 * model; `recount()` and `keysAfter()` let a caller that knows which models
 * changed recount only those.
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
        $recounted = [];

        foreach (array_keys($this->config->counters()) as $class) {
            $model = new $class;
            $after = null;
            $recounted[$class] = 0;

            do {
                $keys = $this->keysAfter($model, $after, $chunk);

                if ($keys === []) {
                    break;
                }

                $this->recount($model, $keys);

                $recounted[$class] += count($keys);
                $after = end($keys);
            } while (count($keys) === $chunk);
        }

        return $recounted;
    }

    /**
     * Writes every counter column of the models with these keys.
     *
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function recount(Model&Viewable $model, array $keys): void
    {
        $values = $this->values($model);

        if ($values === []) {
            return;
        }

        $this->table($model)->whereIn($model->getQualifiedKeyName(), $keys)->update($values);
    }

    /**
     * The next chunk of keys in the model's table, in the order the database
     * sorts them.
     *
     * @return list<int|string>
     */
    public function keysAfter(Model&Viewable $model, int|string|null $after, int $chunk): array
    {
        $key = $model->getQualifiedKeyName();

        /** @var list<int|string> */
        return $this->table($model)
            ->when($after !== null, fn (Builder $query): Builder => $query->where($key, '>', $after))
            ->orderBy($key)
            ->limit($chunk)
            ->pluck($model->getKeyName())
            ->all();
    }

    /**
     * The views of a model were destroyed, so its columns are recounted right
     * away instead of waiting for a run that may never look at it again. A
     * source that cannot be queried in SQL, such as the fake, skips it.
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function destroyed(Model&Viewable $viewable): void
    {
        if (! $this->source instanceof SubquerySource) {
            return;
        }

        $key = $viewable->getKey();

        if ($key === null) {
            return;
        }

        /** @var int|string $key */
        $this->recount($viewable, [$key]);
    }

    /**
     * @return array<string, Builder>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    private function values(Model&Viewable $model): array
    {
        $columns = $this->config->counters()[$model::class] ?? [];

        if ($columns === []) {
            return [];
        }

        $source = $this->source;

        if (! $source instanceof SubquerySource) {
            throw UnsupportedBySource::counters($source);
        }

        $values = [];

        foreach ($columns as $column => $query) {
            $values[$column] = $source->countSubquery($model, $query);
        }

        return $values;
    }

    /**
     * The table is read without the model's scopes, so trashed models are
     * counted too. MySQL reports only the rows whose value changed, so callers
     * count the keys instead of the rows updated.
     */
    private function table(Model $model): Builder
    {
        return $model->getConnection()->table($model->getTable());
    }
}
