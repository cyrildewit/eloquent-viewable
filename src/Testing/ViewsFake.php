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
use CyrildeWit\EloquentViewable\Testing\Exceptions\UnsupportedInFake;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
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

    public function countSubquery(Viewable $viewable, ViewsQuery $query): Builder
    {
        throw UnsupportedInFake::scopes();
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

        PHPUnit::assertTrue(
            $this->recorded($viewable, $callback)->isNotEmpty(),
            "No view of {$name} was recorded".($callback instanceof Closure ? ' that matches the filter.' : '.'),
        );
    }

    /** @param  (Closure(ViewRecord): bool)|null  $callback */
    public function assertNotRecorded(Viewable $viewable, ?Closure $callback = null): void
    {
        $count = $this->recorded($viewable, $callback)->count();

        PHPUnit::assertSame(0, $count, 'A view of '.$this->describe($viewable).' was recorded'.($callback instanceof Closure ? ' that matches the filter.' : '.'));
    }

    public function assertNothingRecorded(): void
    {
        $count = count($this->store->records());

        PHPUnit::assertSame(0, $count, "Views were recorded unexpectedly, {$count} in total.");
    }

    public function assertForgotten(Viewable $viewable): void
    {
        PHPUnit::assertContains(
            [$viewable->getMorphClass(), ViewableKey::of($viewable)],
            $this->forgotten,
            'The views of '.$this->describe($viewable).' were not forgotten.',
        );
    }

    /** @return Collection<int, ViewRecord> */
    private function matching(Viewable $viewable, ViewsQuery $query): Collection
    {
        $start = $query->period?->getStartDateTime();
        $end = $query->period?->getEndDateTime();

        // Resolved before filtering, as the database scope does, so a viewer
        // without a key is refused even when nothing was recorded. A store
        // that keeps strings hands back a string key, so keys are compared
        // as strings.
        $viewer = $query->viewer;
        $viewerKey = $viewer instanceof Model ? (string) ViewerKey::of($viewer) : null;

        return $this->recorded($viewable, fn (ViewRecord $record): bool => (! $start instanceof CarbonInterface || $record->viewedAt->greaterThanOrEqualTo($start))
            && (! $end instanceof CarbonInterface || $record->viewedAt->lessThan($end))
            && ($query->collection === null || $record->collection === $query->collection)
            && (! $viewer instanceof Model || ($record->viewerType === $viewer->getMorphClass() && (string) $record->viewerId === $viewerKey)));
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
