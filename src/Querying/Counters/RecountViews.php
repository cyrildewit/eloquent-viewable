<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Counters;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\StateStore;
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
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Each column is set from the source's correlated count, so under the `rollup`
 * source it includes history whose views are gone. A unique column skips the
 * anonymised views, whose visitor ids no longer link a visitor across days.
 * `handle()` recounts every model; `recount()` and `keysAfter()` let a caller
 * that knows which models changed recount only those. Every write dispatches
 * `CountersRecounted`.
 */
final readonly class RecountViews
{
    public function __construct(
        private ViewSource $source,
        private Config $config,
        private Dispatcher $events,
        private DimensionRegistry $dimensions,
        private StateStore $state,
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
        $counters = $this->config->counters();
        $anonymised = $this->anonymised(array_merge(...array_map(array_values(...), array_values($counters))));

        foreach (array_keys($counters) as $class) {
            $model = new $class;
            $after = null;
            $recounted[$class] = 0;

            do {
                $keys = $this->keysAfter($model, $after, $chunk);

                if ($keys === []) {
                    break;
                }

                $this->recountAnonymisedUntil($model, $keys, $anonymised);

                // MySQL reports only the rows whose value changed, so count the
                // keys instead of the rows updated.
                $recounted[$class] += count($keys);
                $after = end($keys);
            } while (count($keys) === $chunk);
        }

        return $recounted;
    }

    /**
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function recount(Model&Viewable $model, array $keys): void
    {
        $this->recountAnonymisedUntil($model, $keys, $this->anonymised($this->config->counters()[$model::class] ?? []));
    }

    /**
     * Takes the anonymised mark from a caller that already read it, so a
     * recount in chunks reads it once.
     *
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function recountAnonymisedUntil(Model&Viewable $model, array $keys, ?CarbonInterface $anonymised): void
    {
        $values = $this->values($model, $anonymised);
        $scores = $this->config->hotScores()[$model::class] ?? [];

        if ([...$values, ...$scores] === []) {
            return;
        }

        if ($values !== []) {
            $this->table($model)->whereIn($model->getQualifiedKeyName(), $keys)->update($values);
        }

        foreach ($scores as $column => $score) {
            $this->writeHotScores($model, $keys, $column, $score, $anonymised);
        }

        $this->events->dispatch(new CountersRecounted($model::class, $keys));
    }

    /**
     * The next `$chunk` keys greater than `$after`, in key order, so the last
     * key of one chunk is the cursor for the next.
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
    private function writeHotScores(Model&Viewable $model, array $keys, string $column, HotScore $score, ?CarbonInterface $anonymised): void
    {
        if ($keys === []) {
            return;
        }

        $query = $this->config->counters()[$model::class][$column] ?? new ViewsQuery;
        $counts = $this->source->countMany($model, $keys, $this->withoutAnonymised($query, $anonymised));
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
    private function values(Model&Viewable $model, ?CarbonInterface $anonymised): array
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
            $values[$column] = $source->countSubquery($model, $this->withoutAnonymised($this->dimensions->resolve($query), $anonymised));
        }

        return $values;
    }

    /**
     * The state table is only read when a column counts unique visitors.
     *
     * @param  array<array-key, ViewsQuery>  $queries
     */
    private function anonymised(array $queries): ?CarbonInterface
    {
        if (! array_any($queries, static fn (ViewsQuery $query): bool => $query->unique)) {
            return null;
        }

        if (! $this->state->installed()) {
            return null;
        }

        $mark = $this->state->get(StateStore::Anonymised);

        return $mark === null ? null : Carbon::parse($mark);
    }

    /**
     * Visitors of anonymised views can only be counted once per day, so a
     * unique count over them would count a returning visitor again on every
     * day, or on every bucket of the rollups. A period that ends before the
     * mark counts nothing.
     *
     * @throws InvalidPeriod
     */
    private function withoutAnonymised(ViewsQuery $query, ?CarbonInterface $anonymised): ViewsQuery
    {
        if (! $query->unique || ! $anonymised instanceof CarbonInterface) {
            return $query;
        }

        $start = $query->period?->getStartDateTime();

        if ($start instanceof CarbonInterface && $start >= $anonymised) {
            return $query;
        }

        $end = $query->period?->getEndDateTime();

        return $query->withPeriod(Period::create($end instanceof CarbonInterface ? $anonymised->min($end) : $anonymised, $end));
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
