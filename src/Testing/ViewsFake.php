<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Testing;

use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

final class ViewsFake implements ViewSource, ViewStore
{
    private readonly ArrayStore $store;

    /** @var list<array{string, int|string|null}> */
    private array $forgotten = [];

    public function __construct()
    {
        $this->store = new ArrayStore;
    }

    public static function bind(Container $container): self
    {
        $fake = new self;

        $container->instance(ViewStore::class, $fake);
        $container->instance(ViewSource::class, $fake);

        return $fake;
    }

    public function store(ViewRecord $record): void
    {
        $this->store->store($record);
    }

    /** @param  iterable<ViewRecord>  $records */
    public function storeMany(iterable $records): void
    {
        $this->store->storeMany($records);
    }

    public function forget(Viewable $viewable): void
    {
        $this->store->forget($viewable);

        $this->forgotten[] = [$viewable->getMorphClass(), ViewableKey::of($viewable)];
    }

    public function count(Viewable $viewable, ViewsQuery $query): int
    {
        return $this->aggregate($this->matching($viewable, $query), $query);
    }

    /** @return array<string, int> */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        $timezone = $query->timezone ?? Timezone::application();

        $counts = $this->matching($viewable, $query)
            ->groupBy(fn (ViewRecord $record): string => $granularity->floor($record->viewedAt->avoidMutation()->setTimezone($timezone))->format('Y-m-d H:i:s'))
            ->map(fn (Collection $bucket): int => $this->aggregate($bucket, $query))
            ->sortKeys()
            ->all();

        /** @var array<string, int> $counts */
        return $counts;
    }

    /** @return array<string, int> */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array
    {
        $counts = $this->matching($viewable, $query)
            ->groupBy(fn (ViewRecord $record): string => $record->collection ?? '')
            ->map(fn (Collection $collection): int => $this->aggregate($collection, $query))
            ->all();

        /** @var array<string, int> $counts */
        return $counts;
    }

    /**
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        $type = $viewable->getMorphClass();
        $records = $this->matchingRecords($query, fn (ViewRecord $record): bool => $record->viewableType === $type)
            ->groupBy(fn (ViewRecord $record): string => (string) $record->viewableId);

        $counts = [];

        foreach ($keys as $key) {
            $counts[$key] = $this->aggregate($records->get((string) $key, new Collection), $query);
        }

        return $counts;
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
    {
        $type = $viewable?->getMorphClass();

        $rows = [];

        $grouped = $this->matchingRecords($query, fn (ViewRecord $record): bool => $type === null || $record->viewableType === $type)
            ->groupBy(fn (ViewRecord $record): string => "{$record->viewableType}:{$record->viewableId}");

        foreach ($grouped as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();

            $rows[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'count' => $this->aggregate($views, $query)];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['type'], $a['id']] <=> [$a['count'], $b['type'], $b['id']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param  (Closure(ViewRecord): bool)|null  $filter
     * @return Collection<int, ViewRecord>
     */
    public function recorded(Viewable $viewable, ?Closure $filter = null): Collection
    {
        return new Collection($this->store->records())
            ->filter(fn (ViewRecord $record): bool => $record->belongsTo($viewable))
            ->when($filter instanceof Closure, fn (Collection $records): Collection => $records->filter($filter))
            ->values();
    }

    /** @param  (Closure(ViewRecord): bool)|int|null  $callback */
    public function assertRecorded(Viewable $viewable, Closure|int|null $callback = null): void
    {
        $name = $this->describe($viewable);

        if (is_int($callback)) {
            $count = $this->recorded($viewable)->count();

            PHPUnit::assertSame($callback, $count, "Expected {$callback} views of {$name}, {$count} recorded.");

            return;
        }

        $filter = $callback instanceof Closure ? ' that matches the filter' : '';

        PHPUnit::assertTrue(
            $this->recorded($viewable, $callback)->isNotEmpty(),
            "No view of {$name} was recorded{$filter}.",
        );
    }

    /** @param  (Closure(ViewRecord): bool)|null  $callback */
    public function assertNotRecorded(Viewable $viewable, ?Closure $callback = null): void
    {
        $count = $this->recorded($viewable, $callback)->count();

        $name = $this->describe($viewable);

        $filter = $callback instanceof Closure ? ' that matches the filter' : '';

        PHPUnit::assertSame(0, $count, "A view of {$name} was recorded{$filter}.");
    }

    public function assertNothingRecorded(): void
    {
        $count = count($this->store->records());

        PHPUnit::assertSame(0, $count, "Views were recorded unexpectedly, {$count} in total.");
    }

    public function assertForgotten(Viewable $viewable): void
    {
        $name = $this->describe($viewable);

        PHPUnit::assertContains(
            [$viewable->getMorphClass(), ViewableKey::of($viewable)],
            $this->forgotten,
            "The views of {$name} were not forgotten.",
        );
    }

    /** @return Collection<int, ViewRecord> */
    private function matching(Viewable $viewable, ViewsQuery $query): Collection
    {
        return $this->matchingRecords($query, fn (ViewRecord $record): bool => $record->belongsTo($viewable));
    }

    /**
     * @param  Closure(ViewRecord): bool  $filter
     * @return Collection<int, ViewRecord>
     */
    private function matchingRecords(ViewsQuery $query, Closure $filter): Collection
    {
        $start = $query->period?->getStartDateTime();
        $end = $query->period?->getEndDateTime();

        $viewer = $query->viewer;
        $viewerKey = $viewer instanceof Model ? (string) ViewerKey::of($viewer) : null;

        return new Collection($this->store->records())
            ->filter($filter)
            ->filter(fn (ViewRecord $record): bool => (! $start instanceof CarbonInterface || $record->viewedAt->greaterThanOrEqualTo($start))
                && (! $end instanceof CarbonInterface || $record->viewedAt->lessThan($end))
                && ($query->collection === null || $record->collection === $query->collection)
                && (! $viewer instanceof Model || ($record->viewerType === $viewer->getMorphClass() && (string) $record->viewerId === $viewerKey)))
            ->values();
    }

    /** @param  Collection<int, ViewRecord>  $records */
    private function aggregate(Collection $records, ViewsQuery $query): int
    {
        if (! $query->unique) {
            return $records->count();
        }

        return $records->pluck('visitor')->filter()->unique()->count();
    }

    private function describe(Viewable $viewable): string
    {
        $key = ViewableKey::of($viewable);

        return $key === null ? $viewable->getMorphClass() : "{$viewable->getMorphClass()} {$key}";
    }
}
