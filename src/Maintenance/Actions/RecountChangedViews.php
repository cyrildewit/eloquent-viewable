<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Actions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Maintenance\Data\RecountRun;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\TracksChangedViewables;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Support\Chunks;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\HotScore;
use CyrildeWit\EloquentViewable\Support\Progress;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use JsonException;

/**
 * This action recounts only the models whose counter columns can have changed
 * since the last recount: those with new views, those with views that left
 * the period of a column, and, for unique columns, those with views that were
 * anonymised since. Everything else about a count stays the same between two
 * runs, so the rest of the table is left alone.
 *
 * Every model is recounted on the first run, after the columns or the source
 * changed, through a source that does not track the viewables a count may
 * have changed for, on request, and after views were pruned when the source
 * says a count changes with them.
 *
 * What a recount saw is kept in the state table. A run that reaches its
 * deadline keeps where it got to, and the next run finishes that recount
 * before it starts a new one. Without the state table every run recounts
 * every model and ignores the deadline, because it has nowhere to keep its
 * place.
 *
 * @phpstan-type Snapshot array{signature: array<int|string, mixed>, id: ?int, starts: array<string, ?string>, anonymised: ?string, pruned: ?string}
 * @phpstan-type Pending array{target: Snapshot, full: bool, after: int|string|null}
 */
final readonly class RecountChangedViews
{
    private const string Prefix = 'counters:';

    private const string Pending = ':pending';

    public function __construct(
        private RecountViews $recount,
        private ViewSource $source,
        private Config $config,
        private StateStore $state,
        private View $view,
    ) {}

    /**
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws UnsupportedBySource
     */
    public function handle(int $chunk, ?Deadline $deadline = null, bool $full = false): RecountRun
    {
        if (! $this->state->installed()) {
            return new RecountRun($this->recount->handle($chunk), stopped: false);
        }

        $deadline ??= Deadline::none();
        $recounted = [];

        foreach ($this->config->counters() as $class => $columns) {
            $progress = $this->recountClass(new $class, $columns, $chunk, $deadline, $full);
            $recounted[$class] = $progress->count;

            if ($progress->stopped) {
                return new RecountRun($recounted, stopped: true);
            }
        }

        return new RecountRun($recounted, stopped: false);
    }

    /**
     * Purging bot views deletes views without saying whose they were, so the
     * next run recounts every model of every class.
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    public function recountEveryModelNextRun(): void
    {
        if (! $this->state->installed()) {
            return;
        }

        foreach (array_keys($this->config->counters()) as $class) {
            $this->forget(new $class);
        }
    }

    /**
     * A model's columns are recounted right away. Views destroyed across a
     * whole type make the next run recount every model of it.
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function destroyed(Viewable $viewable): void
    {
        if (! array_key_exists($viewable::class, $this->config->counters())) {
            return;
        }

        /**
         * A class with counter columns is always a model.
         *
         * @var Model&Viewable $viewable
         */
        if (ViewableKey::of($viewable) !== null) {
            $this->recount->destroyed($viewable);

            return;
        }

        if (! $this->state->installed()) {
            return;
        }

        $this->forget($viewable);
    }

    /**
     * The models of a type whose views were erased are recounted right away,
     * because an erased view no longer says whose it was.
     *
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws UnsupportedBySource
     */
    public function erased(string $type, array $keys): void
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! array_key_exists($class, $this->config->counters())) {
            return;
        }

        $this->recount->recount(new $class, $keys);
    }

    private function forget(Model $model): void
    {
        $this->state->forget($this->name($model));
        $this->state->forget($this->name($model).self::Pending);
    }

    /**
     * @param  array<string, ViewsQuery>  $columns
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws UnsupportedBySource
     */
    private function recountClass(Model&Viewable $model, array $columns, int $chunk, Deadline $deadline, bool $full): Progress
    {
        /** @var ?Snapshot $committed */
        $committed = $this->read($this->name($model));
        /** @var ?Pending $pending */
        $pending = $full ? null : $this->read($this->name($model).self::Pending);

        $tracking = $this->source instanceof TracksChangedViewables ? $this->source : null;

        if ($pending === null) {
            $target = $this->snapshot($model, $columns);

            $pending = [
                'target' => $target,
                'full' => $full || $committed === null || $tracking === null || $this->needsEveryModel($tracking, $committed, $target),
                'after' => null,
            ];
        }

        $keys = $pending['full'] || $committed === null || $tracking === null
            ? null
            : $this->changedKeys($tracking, $model, $columns, $committed, $pending['target']);
        $after = $pending['after'];

        $progress = Chunks::run(
            $deadline,
            $chunk,
            function () use ($keys, $model, $chunk, &$after): array {
                return $keys === null
                    ? $this->recount->keysAfter($model, $after, $chunk)
                    : $this->changedAfter($keys, $after, $chunk);
            },
            function (array $batch) use ($model, &$after): int {
                $this->recount->recount($model, $batch);
                $after = array_last($batch);

                return count($batch);
            },
        );

        if ($progress->stopped) {
            $this->write($this->name($model).self::Pending, [...$pending, 'after' => $after]);

            return $progress;
        }

        $this->write($this->name($model), $pending['target']);
        $this->state->forget($this->name($model).self::Pending);

        return $progress;
    }

    /**
     * Captures what a recount sees as it starts: the last view, where the
     * period of each column starts, and how far views are anonymised and
     * pruned. The signature says which columns and source it was taken for,
     * and how each hot score is worked out.
     *
     * @param  array<string, ViewsQuery>  $columns
     * @return Snapshot
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     */
    private function snapshot(Model $model, array $columns): array
    {
        $starts = [];
        $signature = [$this->source::class];
        $hot = $this->config->hotScores()[$model::class] ?? [];

        foreach ($columns as $column => $query) {
            $starts[$column] = $this->format($query->period?->getStartDateTime());
            $signature[$column] = [$query->period?->cacheSignature(), $query->collection, $query->unique, ...$this->hotSignature($hot, $column)];
        }

        $max = $this->view->newQuery()->toBase()->max('id');

        return [
            'signature' => $signature,
            'id' => $max === null ? null : (int) $max, // @phpstan-ignore cast.int (an integer column)
            'starts' => $starts,
            'anonymised' => $this->state->get(StateStore::Anonymised),
            'pruned' => $this->state->get(StateStore::Pruned),
        ];
    }

    /**
     * A plain count keeps the signature it had before hot scores existed, so
     * adding the option to one column does not recount the others.
     *
     * @param  array<string, HotScore>  $hot
     * @return list<string>
     */
    private function hotSignature(array $hot, string $column): array
    {
        if (! isset($hot[$column])) {
            return [];
        }

        return [$hot[$column]->signature()];
    }

    /**
     * @param  Snapshot  $committed
     * @param  Snapshot  $target
     */
    private function needsEveryModel(TracksChangedViewables $source, array $committed, array $target): bool
    {
        if ($committed['signature'] !== $target['signature']) {
            return true;
        }

        return $source->countsChangeWhenPruned() && $committed['pruned'] !== $target['pruned'];
    }

    /**
     * Finds the keys of the models whose counts can have changed. They are
     * sorted the way they are compared, so a recount that stopped halfway
     * picks up after the last key it wrote.
     *
     * @param  array<string, ViewsQuery>  $columns
     * @param  Snapshot  $committed
     * @param  Snapshot  $target
     * @return list<int|string>
     */
    private function changedKeys(TracksChangedViewables $source, Model&Viewable $model, array $columns, array $committed, array $target): array
    {
        $type = $model->getMorphClass();
        $keys = $this->viewedAfter($type, $committed['id'], $target['id']);

        foreach (array_keys($columns) as $column) {
            [$from, $until] = [$committed['starts'][$column] ?? null, $target['starts'][$column] ?? null];

            if ($until !== null && $from !== $until) {
                $keys = [...$keys, ...$source->keysViewedBetween($type, $from, $until)];
            }
        }

        if ($this->hasUniqueColumn($columns)) {
            $keys = [...$keys, ...$this->anonymisedBetween($type, $committed['anonymised'], $target['anonymised'])];
        }

        $keys = array_values(array_unique($keys));

        sort($keys);

        return $keys;
    }

    /** @param  array<string, ViewsQuery>  $columns */
    private function hasUniqueColumn(array $columns): bool
    {
        return array_any($columns, fn (ViewsQuery $query): bool => $query->unique);
    }

    /** @return list<int|string> */
    private function viewedAfter(string $type, ?int $from, ?int $until): array
    {
        if ($until === null) {
            return [];
        }

        /** @var list<int|string> */
        return $this->view
            ->newQuery()
            ->toBase()
            ->where('viewable_type', $type)
            ->when($from, fn (Builder $query, int $from): Builder => $query->where('id', '>', $from))
            ->where('id', '<=', $until)
            ->distinct()
            ->pluck('viewable_id')
            ->all();
    }

    /**
     * Finds the models with views anonymised since the last run, which are
     * in the views table whatever the source, because anonymising rewrites
     * the views themselves.
     *
     * @return list<int|string>
     */
    private function anonymisedBetween(string $type, ?string $from, ?string $until): array
    {
        if ($until === null || $from === $until) {
            return [];
        }

        /** @var list<int|string> */
        return $this->view
            ->newQuery()
            ->toBase()
            ->where('viewable_type', $type)
            ->when($from, fn (Builder $query, string $from): Builder => $query->where('viewed_at', '>=', $from))
            ->where('viewed_at', '<', $until)
            ->distinct()
            ->pluck('viewable_id')
            ->all();
    }

    /**
     * @param  list<int|string>  $keys
     * @return list<int|string>
     */
    private function changedAfter(array $keys, int|string|null $after, int $chunk): array
    {
        if ($after !== null) {
            $keys = array_values(array_filter($keys, fn (int|string $key): bool => $key > $after));
        }

        return array_slice($keys, 0, $chunk);
    }

    private function name(Model $model): string
    {
        return self::Prefix.$model->getMorphClass();
    }

    /**
     * @return ?array<string, mixed>
     *
     * @throws JsonException
     */
    private function read(string $name): ?array
    {
        $value = $this->state->get($name);

        if ($value === null) {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws JsonException
     */
    private function write(string $name, array $value): void
    {
        $this->state->put($name, json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function format(?CarbonInterface $moment): ?string
    {
        return $moment?->format(StateStore::Format);
    }
}
