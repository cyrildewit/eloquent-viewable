<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Milestones\Actions;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidConfiguration;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Milestones\Events\ViewMilestoneReached;
use CyrildeWit\EloquentViewable\Milestones\Exceptions\MilestonesNotInstalled;
use CyrildeWit\EloquentViewable\Milestones\MilestoneMarks;
use CyrildeWit\EloquentViewable\Querying\Rollups\Contracts\StateStore;
use CyrildeWit\EloquentViewable\Support\Config;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use JsonException;
use stdClass;

/**
 * This action compares the counter columns a recount just wrote with their
 * thresholds. A threshold is crossed when it lies above a model's mark and at
 * or below its count; the mark then moves up to the count. The marks of a
 * batch move in one transaction and the events are dispatched once it is
 * committed, so a listener that throws never holds a mark back.
 *
 * The thresholds a column was armed with are kept in the state table. When
 * they differ from the config, the next recount arms the column again over
 * every model: a threshold it was armed with still fires, and a new one is
 * covered silently for every model already past it. So enabling milestones,
 * or adding one, sends nothing for what happened before.
 *
 * @internal
 */
final readonly class CheckMilestones
{
    private const string Prefix = 'milestones:';

    private const int Chunk = 1_000;

    public function __construct(
        private Config $config,
        private MilestoneMarks $marks,
        private StateStore $state,
        private Dispatcher $events,
    ) {}

    /**
     * Check the models with these keys, after their counter columns were
     * written.
     *
     * @param  class-string<Model&Viewable>  $class
     * @param  list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws MilestonesNotInstalled
     */
    public function recounted(string $class, array $keys): void
    {
        $columns = $this->config->milestones()[$class] ?? [];

        if ($columns === []) {
            return;
        }

        $this->marks->ensureInstalled();

        $model = new $class;

        foreach ($columns as $column => $thresholds) {
            $armed = $this->armedWith($model, $column);

            if ($armed !== $thresholds) {
                $this->check($model, $column, $thresholds, array_values(array_diff($thresholds, $armed ?? [])));
                $this->arm($model, $column, $thresholds);

                continue;
            }

            $this->check($model, $column, $thresholds, keys: $keys);
        }
    }

    /**
     * Raise the mark of every model to its count without dispatching, so
     * nothing it already passed fires. It returns how many marks moved per
     * class.
     *
     * @return array<class-string<Model&Viewable>, int>
     *
     * @throws InvalidConfiguration
     * @throws InvalidPeriod
     * @throws JsonException
     * @throws MilestonesNotInstalled
     */
    public function seed(?string $only = null): array
    {
        $this->marks->ensureInstalled();

        $seeded = [];
        $milestones = $this->config->milestones();

        if ($only !== null) {
            $milestones = array_intersect_key($milestones, [$only => true]);
        }

        foreach ($milestones as $class => $columns) {
            $model = new $class;
            $seeded[$class] = 0;

            foreach ($columns as $column => $thresholds) {
                $seeded[$class] += $this->check($model, $column, $thresholds, silent: $thresholds);

                $this->arm($model, $column, $thresholds);
            }
        }

        return $seeded;
    }

    /**
     * Move the marks of the models whose count crossed a threshold. Crossing
     * one dispatches an event, unless it is one of `$silent`, which only moves
     * the mark. Without keys every model is checked. It returns how many marks
     * moved.
     *
     * @param  non-empty-list<int>  $thresholds
     * @param  list<int>  $silent
     * @param  ?list<int|string>  $keys
     *
     * @throws InvalidConfiguration
     */
    private function check(Model&Viewable $model, string $column, array $thresholds, array $silent = [], ?array $keys = null): int
    {
        $firing = array_values(array_diff($thresholds, $silent));
        $type = $model->getMorphClass();
        $moved = 0;

        foreach ($this->counts($model, $column, min($thresholds), $keys) as $counts) {
            $marks = $this->marks->of($type, $column, array_keys($counts));

            $events = $this->marks->transaction(function () use ($counts, $marks, $type, $column, $firing, $silent, &$moved): array {
                $events = [];

                foreach ($counts as $key => $count) {
                    $mark = $marks[(string) $key] ?? null;
                    $passed = $this->crossed($firing, $mark ?? 0, $count);

                    $moves = $passed !== [] || $this->crossed($silent, $mark ?? 0, $count) !== [];

                    if (! $moves) {
                        continue;
                    }

                    if (! $this->marks->raise($type, $key, $column, $count, $mark)) {
                        continue;
                    }

                    $moved++;

                    if ($passed === []) {
                        continue;
                    }

                    $events[] = new ViewMilestoneReached($type, $key, $column, array_last($passed), $count, $passed);
                }

                return $events;
            });

            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
        }

        return $moved;
    }

    /**
     * It returns the thresholds that lie above the mark and at or below the
     * count.
     *
     * @param  list<int>  $thresholds
     * @return list<int>
     */
    private function crossed(array $thresholds, int $mark, int $count): array
    {
        return array_values(array_filter($thresholds, fn (int $threshold): bool => $threshold > $mark && $threshold <= $count));
    }

    /**
     * It reads the counts of the models at or above the lowest threshold,
     * keyed by model key, a chunk at a time. The table is read without the
     * model's scopes, the same way the recount writes it, so trashed models
     * count too.
     *
     * @param  ?list<int|string>  $keys
     * @return Generator<int, non-empty-array<int|string, int>>
     */
    private function counts(Model $model, string $column, int $lowest, ?array $keys): Generator
    {
        $name = $model->getKeyName();
        $after = null;

        do {
            $rows = $model->getConnection()
                ->table($model->getTable())
                ->where($column, '>=', $lowest)
                ->when($keys !== null, fn (Builder $query): Builder => $query->whereIn($name, $keys ?? []))
                ->when($after !== null, fn (Builder $query): Builder => $query->where($name, '>', $after))
                ->orderBy($name)
                ->limit(self::Chunk)
                ->get([$name, $column]);

            $counts = [];

            /** @var stdClass $row */
            foreach ($rows as $row) {
                /** @var int|string $key */
                $key = $row->{$name};

                $counts[$key] = (int) $row->{$column}; // @phpstan-ignore cast.int (an integer column)
                $after = $key;
            }

            if ($counts !== []) {
                yield $counts;
            }
        } while (count($rows) === self::Chunk);
    }

    /**
     * @return ?list<int>
     *
     * @throws JsonException
     */
    private function armedWith(Model $model, string $column): ?array
    {
        $value = $this->state->get($this->name($model, $column));

        if ($value === null) {
            return null;
        }

        /** @var list<int> */
        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<int>  $thresholds
     *
     * @throws JsonException
     */
    private function arm(Model $model, string $column, array $thresholds): void
    {
        $this->state->put($this->name($model, $column), json_encode($thresholds, JSON_THROW_ON_ERROR));
    }

    private function name(Model $model, string $column): string
    {
        $prefix = self::Prefix;

        return "{$prefix}{$model->getMorphClass()}:{$column}";
    }
}
