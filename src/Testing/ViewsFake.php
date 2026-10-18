<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Testing;

use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Stores\ArrayPresenceStore;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Querying\Sources\ArraySource;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Stands in for the store, the source and the presence store at once, so a
 * test records views, counts them and asserts on both without a database.
 *
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 */
final class ViewsFake implements CountsBy, CountsByWindow, CountsVisitFrequency, PresenceStore, RanksAlsoViewed, RanksRecommendations, RanksTrending, ViewSource, ViewStore
{
    private readonly ArrayStore $store;

    private readonly ArraySource $source;

    private readonly ArrayPresenceStore $presence;

    /** @var list<Sighting> */
    private array $sightings = [];

    /** @var list<Sighting> */
    private array $departures = [];

    /** @var list<array{string, int|string|null}> */
    private array $forgotten = [];

    public function __construct()
    {
        $this->store = new ArrayStore;
        $this->source = new ArraySource(fn (): array => $this->store->records());
        $this->presence = new ArrayPresenceStore;
    }

    public static function bind(Container $container): self
    {
        $fake = new self;

        $container->instance(ViewStore::class, $fake);
        $container->instance(ViewSource::class, $fake);
        $container->instance(PresenceStore::class, $fake);

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
        return $this->source->count($viewable, $query);
    }

    /** @return array<string, int> */
    public function countByInterval(Viewable $viewable, ViewsQuery $query, Granularity $granularity): array
    {
        return $this->source->countByInterval($viewable, $query, $granularity);
    }

    /** @return array<string, int> */
    public function countByCollection(Viewable $viewable, ViewsQuery $query): array
    {
        return $this->source->countByCollection($viewable, $query);
    }

    public function countBy(Viewable $viewable, ViewsQuery $query, DimensionDefinition $dimension, ?int $limit = null): DimensionCounts
    {
        return $this->source->countBy($viewable, $query, $dimension, $limit);
    }

    /** @return array<int, int> */
    public function visitFrequency(Viewable $viewable, ViewsQuery $query): array
    {
        return $this->source->visitFrequency($viewable, $query);
    }

    /**
     * @param  non-empty-list<int|string>  $keys
     * @return array<int|string, int>
     */
    public function countMany(Viewable $viewable, array $keys, ViewsQuery $query): array
    {
        return $this->source->countMany($viewable, $keys, $query);
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function top(?Viewable $viewable, ViewsQuery $query, int $limit): array
    {
        return $this->source->top($viewable, $query, $limit);
    }

    /**
     * @param  non-empty-list<Period>  $references
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     */
    public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array
    {
        return $this->source->countByWindow($viewable, $query, $references, $minimum);
    }

    /** @return list<array{type: string, id: int|string, count: int, score: float}> */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array
    {
        return $this->source->trending($viewable, $query, $decay, $limit);
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array
    {
        return $this->source->alsoViewed($viewable, $among, $query, $limit, $minimum, $maxVisitors);
    }

    /** @return RecommendationPairs */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array
    {
        return $this->source->recommendationPairs($request, $query);
    }

    public function touch(Sighting $sighting): void
    {
        $this->sightings[] = $sighting;

        $this->presence->touch($sighting);
    }

    public function leave(Sighting $sighting): void
    {
        $this->departures[] = $sighting;

        $this->presence->leave($sighting);
    }

    /**
     * @param  list<Scope>  $scopes
     * @return list<int>
     */
    public function countVisitors(array $scopes, CarbonInterface $since): array
    {
        return $this->presence->countVisitors($scopes, $since);
    }

    /** @return list<Reference> */
    public function active(?string $type, CarbonInterface $since, int $limit): array
    {
        return $this->presence->active($type, $since, $limit);
    }

    /** @return list<Reference> */
    public function viewers(Scope $scope, CarbonInterface $since, int $limit): array
    {
        return $this->presence->viewers($scope, $since, $limit);
    }

    /**
     * Puts the given number of visitors on the viewable now, so a page that
     * shows a live count can be tested without sending heartbeats.
     *
     * @throws InvalidViewable
     */
    public function present(Viewable $viewable, int $visitors = 1, ?string $collection = null): self
    {
        $key = ViewableKey::of($viewable) ?? throw InvalidViewable::presentWithoutKey($viewable::class);

        for ($visitor = 1; $visitor <= $visitors; $visitor++) {
            $this->presence->touch(Sighting::of($viewable->getMorphClass(), $key, "fake-visitor-{$visitor}", $collection));
        }

        return $this;
    }

    public function assertPresent(Viewable $viewable): void
    {
        $name = $this->describe($viewable);

        PHPUnit::assertNotEmpty(
            $this->sightingsOf($this->sightings, $viewable),
            "No visitor was kept active on {$name}.",
        );
    }

    public function assertNotPresent(Viewable $viewable): void
    {
        $name = $this->describe($viewable);

        PHPUnit::assertEmpty(
            $this->sightingsOf($this->sightings, $viewable),
            "A visitor was kept active on {$name}.",
        );
    }

    public function assertLeft(Viewable $viewable): void
    {
        $name = $this->describe($viewable);

        PHPUnit::assertNotEmpty(
            $this->sightingsOf($this->departures, $viewable),
            "No visitor left {$name}.",
        );
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

    /**
     * @param  list<Sighting>  $sightings
     * @return list<Sighting>
     */
    private function sightingsOf(array $sightings, Viewable $viewable): array
    {
        $type = $viewable->getMorphClass();
        $key = (string) ViewableKey::of($viewable);

        return array_values(array_filter(
            $sightings,
            static fn (Sighting $sighting): bool => $sighting->type === $type && (string) $sighting->key === $key,
        ));
    }

    private function describe(Viewable $viewable): string
    {
        $key = ViewableKey::of($viewable);

        if ($key === null) {
            return $viewable->getMorphClass();
        }

        return "{$viewable->getMorphClass()} {$key}";
    }
}
