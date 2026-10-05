<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Maintenance\Actions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Maintenance\Data\RecountRun;
use CyrildeWit\EloquentViewable\Models\View;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Counters\RecountViews;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Querying\Rollups\Grouping;
use CyrildeWit\EloquentViewable\Querying\Rollups\Models\ViewRollup;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupPolicy;
use CyrildeWit\EloquentViewable\Querying\Rollups\RollupSource;
use CyrildeWit\EloquentViewable\Querying\Sources\DatabaseSource;
use CyrildeWit\EloquentViewable\Support\Config;
use CyrildeWit\EloquentViewable\Support\Deadline;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Model;
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
 * changed, through a source other than the shipped two, on request, and after
 * views were pruned under the `database` source, because a deleted view no
 * longer says whose it was. Under the `rollup` source pruning changes no
 * count, because nothing is pruned before it is folded.
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
        private ViewRollup $rollup,
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
            $result = $this->recountClass(new $class, $columns, $chunk, $deadline, $full);
            $recounted[$class] = $result['models'];

            if (! $result['finished']) {
                return new RecountRun($recounted, stopped: true);
            }
        }

        return new RecountRun($recounted, stopped: false);
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

        $this->state->forget($this->name($viewable));
        $this->state->forget($this->name($viewable).self::Pending);
    }

    /**
     * @param  array<string, ViewsQuery>  $columns
     * @return array{models: int, finished: bool}
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws UnsupportedBySource
     */
    private function recountClass(Model&Viewable $model, array $columns, int $chunk, Deadline $deadline, bool $full): array
    {
        /** @var ?Snapshot $committed */
        $committed = $this->read($this->name($model));
        /** @var ?Pending $pending */
        $pending = $full ? null : $this->read($this->name($model).self::Pending);

        if ($pending === null) {
            $target = $this->snapshot($columns);

            $pending = [
                'target' => $target,
                'full' => $full || $committed === null || $this->needsEveryModel($committed, $target),
                'after' => null,
            ];
        }

        $keys = $pending['full'] || $committed === null
            ? null
            : $this->changedKeys($model, $columns, $committed, $pending['target']);
        $after = $pending['after'];
        $recounted = 0;

        while (true) {
            if ($deadline->passed()) {
                $this->write($this->name($model).self::Pending, [...$pending, 'after' => $after]);

                return ['models' => $recounted, 'finished' => false];
            }

            $batch = $keys === null
                ? $this->recount->keysAfter($model, $after, $chunk)
                : $this->changedAfter($keys, $after, $chunk);

            if ($batch === []) {
                break;
            }

            $this->recount->recount($model, $batch);

            $recounted += count($batch);
            $after = end($batch);

            if (count($batch) < $chunk) {
                break;
            }
        }

        $this->write($this->name($model), $pending['target']);
        $this->state->forget($this->name($model).self::Pending);

        return ['models' => $recounted, 'finished' => true];
    }

    /**
     * What a recount sees as it starts: the last view, where the period of
     * each column starts, and how far views are anonymised and pruned. The
     * signature says which columns and source it was taken for.
     *
     * @param  array<string, ViewsQuery>  $columns
     * @return Snapshot
     */
    private function snapshot(array $columns): array
    {
        $starts = [];
        $signature = [$this->source::class];

        foreach ($columns as $column => $query) {
            $starts[$column] = $this->format($query->period?->getStartDateTime());
            $signature[$column] = [$query->period?->cacheSignature(), $query->collection, $query->unique];
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
     * @param  Snapshot  $committed
     * @param  Snapshot  $target
     */
    private function needsEveryModel(array $committed, array $target): bool
    {
        if (! $this->isShippedSource()) {
            return true;
        }

        if ($committed['signature'] !== $target['signature']) {
            return true;
        }

        if (! $this->source instanceof DatabaseSource) {
            return false;
        }

        return $committed['pruned'] !== $target['pruned'];
    }

    /**
     * The shipped sources are the ones whose counts change only in the ways
     * this action looks for.
     */
    private function isShippedSource(): bool
    {
        if ($this->source instanceof DatabaseSource) {
            return true;
        }

        return $this->source instanceof RollupSource;
    }

    /**
     * Sorted the way they are compared, so a recount that stopped halfway
     * picks up after the last key it wrote.
     *
     * @param  array<string, ViewsQuery>  $columns
     * @param  Snapshot  $committed
     * @param  Snapshot  $target
     * @return list<int|string>
     */
    private function changedKeys(Model&Viewable $model, array $columns, array $committed, array $target): array
    {
        $type = $model->getMorphClass();
        $keys = $this->viewedAfter($type, $committed['id'], $target['id']);

        foreach (array_keys($columns) as $column) {
            $keys = [...$keys, ...$this->viewedBetween($type, $committed['starts'][$column] ?? null, $target['starts'][$column] ?? null, rollups: true)];
        }

        if ($this->hasUniqueColumn($columns)) {
            $keys = [...$keys, ...$this->viewedBetween($type, $committed['anonymised'], $target['anonymised'], rollups: false)];
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
     * The models with views in `[from, until)`. Under the `rollup` source the
     * views may be gone already, so the buckets that start in it count too.
     *
     * @return list<int|string>
     */
    private function viewedBetween(string $type, ?string $from, ?string $until, bool $rollups): array
    {
        if ($until === null) {
            return [];
        }

        if ($from === $until) {
            return [];
        }

        /** @var list<int|string> $keys */
        $keys = $this->view
            ->newQuery()
            ->toBase()
            ->where('viewable_type', $type)
            ->when($from, fn (Builder $query, string $from): Builder => $query->where('viewed_at', '>=', $from))
            ->where('viewed_at', '<', $until)
            ->distinct()
            ->pluck('viewable_id')
            ->all();

        if (! $rollups) {
            return $keys;
        }

        if (! $this->source instanceof RollupSource) {
            return $keys;
        }

        /** @var list<int|string> $buckets */
        $buckets = $this->rollup
            ->newQuery()
            ->toBase()
            ->where('rollup', RollupPolicy::BuiltIn)
            ->whereIn('grouping', [Grouping::Viewable->stored(), Grouping::ViewableCollection->stored()])
            ->where('viewable_type', $type)
            ->when($from, fn (Builder $query, string $from): Builder => $query->where('bucket_start', '>=', $from))
            ->where('bucket_start', '<', $until)
            ->distinct()
            ->pluck('viewable_id')
            ->all();

        return [...$keys, ...$buckets];
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
