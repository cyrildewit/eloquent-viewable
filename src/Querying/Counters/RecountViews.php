<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Dimensions\DimensionRegistry;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Counters\Events\CountersRecounted;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\HotScore;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Each column is set from the source's correlated count, so under the `rollup`
 * source it includes history whose views are gone. `handle()` recounts every
 * model; `recount()` and `keysAfter()` let a caller that knows which models
 * changed recount only those. Every write dispatches `CountersRecounted`.
 */
final readonly class RecountViews
{
    public function __construct(
        private ViewSource $source,
        private Config $config,
        private Dispatcher $events,
        private DimensionRegistry $dimensions,
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
        $scores = $this->config->hotScores()[$model::class] ?? [];

        if ([...$values, ...$scores] === []) {
            return;
        }

        if ($values !== []) {
            $this->table($model)->whereIn($model->getQualifiedKeyName(), $keys)->update($values);
        }

        foreach ($scores as $column => $score) {
            $this->writeHotScores($model, $keys, $column, $score);
        }

        $this->events->dispatch(new CountersRecounted($model::class, $keys));
    }

    /**
     * Returns the next chunk of keys in the model's table, in the order the
     * database sorts them.
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
     * A hot score needs a logarithm, which SQLite has no function for, so it
     * is worked out here from the counts and written in one statement. The
     * scores are numbers this method made, so they are written as literals,
     * which every driver reads as a number.
     *
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    private function writeHotScores(Model&Viewable $model, array $keys, string $column, HotScore $score): void
    {
        if ($keys === []) {
            return;
        }

        $query = $this->config->counters()[$model::class][$column] ?? new ViewsQuery;
        $counts = $this->source->countMany($model, $keys, $query);
        $grammar = $model->getConnection()->getQueryGrammar();
        $key = $model->getKeyName();

        $moments = $this->table($model)->whereIn($key, $keys)->pluck($score->from, $key);

        if ($moments->isEmpty()) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($moments as $id => $moment) {
            $at = $moment === null ? null : Carbon::parse($moment); // @phpstan-ignore argument.type (a timestamp column)
            $value = number_format($score->score($counts[$id] ?? 0, $at), 10, '.', '');

            $cases[] = "when ? then {$value}";
            $bindings[] = $id;
        }

        $whens = implode(' ', $cases);
        $placeholders = implode(', ', array_fill(0, count($bindings), '?'));

        $model->getConnection()->update(
            "update {$grammar->wrapTable($model->getTable())} set {$grammar->wrap($column)} = case {$grammar->wrap($key)} {$whens} end where {$grammar->wrap($key)} in ({$placeholders})",
            [...$bindings, ...$bindings],
        );
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
        $columns = array_diff_key(
            $this->config->counters()[$model::class] ?? [],
            $this->config->hotScores()[$model::class] ?? [],
        );

        if ($columns === []) {
            return [];
        }

        $source = $this->source;

        if (! $source instanceof SubquerySource) {
            throw UnsupportedBySource::counters($source);
        }

        $values = [];

        foreach ($columns as $column => $query) {
            $values[$column] = $source->countSubquery($model, $this->dimensions->resolve($query));
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
