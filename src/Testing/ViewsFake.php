<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Testing;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Presence\Contracts\PresenceStore;
use CyrildeWit\EloquentViewable\Presence\Data\Reference;
use CyrildeWit\EloquentViewable\Presence\Data\Scope;
use CyrildeWit\EloquentViewable\Presence\Data\Sighting;
use CyrildeWit\EloquentViewable\Presence\Stores\ArrayPresenceStore;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Step;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Recording\Contracts\ViewStore;
use CyrildeWit\EloquentViewable\Recording\Stores\ArrayStore;
use CyrildeWit\EloquentViewable\Support\AnonymisedVisitor;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 */
final class ViewsFake implements CountsVisitFrequency, PresenceStore, RanksAlsoViewed, RanksRecommendations, RanksTrending, ViewSource, ViewStore
{
    private readonly ArrayStore $store;

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

    /** @return array<int, int> */
    public function visitFrequency(Viewable $viewable, ViewsQuery $query): array
    {
        $timezone = $query->timezone ?? Timezone::application();

        $days = $this->matching($viewable, $query)
            ->filter(static fn (ViewRecord $record): bool => $record->visitor !== null && ! str_starts_with($record->visitor, AnonymisedVisitor::Prefix))
            ->groupBy(static fn (ViewRecord $record): string => (string) $record->visitor)
            ->map(static fn (Collection $views): int => $views->map(static fn (ViewRecord $record): string => $record->viewedAt->avoidMutation()->setTimezone($timezone)->format('Y-m-d'))->unique()->count());

        $counts = [];

        foreach ($days as $visited) {
            $counts[$visited] = ($counts[$visited] ?? 0) + 1;
        }

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
     * Applies the steps of the decay to the recorded views, as the database
     * source does in SQL.
     *
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     */
    public function trending(?Viewable $viewable, ViewsQuery $query, Decay $decay, int $limit): array
    {
        $type = $viewable?->getMorphClass();
        $steps = $decay->steps();

        $grouped = $this->matchingRecords($decay->narrow($query), fn (ViewRecord $record): bool => $type === null || $record->viewableType === $type)
            ->groupBy(fn (ViewRecord $record): string => "{$record->viewableType}:{$record->viewableId}");

        $rows = [];

        foreach ($grouped as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();
            $count = 0;
            $score = 0;

            foreach ($views->groupBy(fn (ViewRecord $record): string => (string) $this->stepOf($record, $steps)) as $index => $inStep) {
                $aggregate = $this->aggregate($inStep, $query);
                $count += $aggregate;
                $score += ($steps[$index]->weight ?? 0) * $aggregate;
            }

            $rows[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'count' => $count, 'score' => $score];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['type'], $a['id']] <=> [$a['score'], $b['type'], $b['id']]);

        return array_map(static fn (array $row): array => [...$row, 'score' => $row['score'] / Decay::Scale], array_slice($rows, 0, $limit));
    }

    /** @return list<array{type: string, id: int|string, count: int}> */
    public function alsoViewed(Viewable $viewable, ?Viewable $among, ViewsQuery $query, int $limit, int $minimum, ?int $maxVisitors): array
    {
        $records = $this->matchingRecords($query, static fn (ViewRecord $record): bool => $record->visitor !== null);

        /** @var list<array{string, float}> $seen */
        $seen = $records
            ->filter(static fn (ViewRecord $record): bool => $record->belongsTo($viewable))
            ->groupBy(static fn (ViewRecord $record): string => (string) $record->visitor)
            ->map(static fn (Collection $views): array => [
                (string) $views->first()?->visitor,
                (float) $views->max(static fn (ViewRecord $record): float => $record->viewedAt->getPreciseTimestamp()),
            ])
            ->values()
            ->all();

        if ($maxVisitors !== null) {
            usort($seen, static fn (array $a, array $b): int => [$b[1], $a[0]] <=> [$a[1], $b[0]]);
            $seen = array_slice($seen, 0, $maxVisitors);
        }

        $visitors = array_column($seen, 0);
        $amongType = $among?->getMorphClass();

        $grouped = $records
            ->filter(static fn (ViewRecord $record): bool => in_array($record->visitor, $visitors, true)
                && ! $record->belongsTo($viewable)
                && ($amongType === null || $record->viewableType === $amongType))
            ->groupBy(static fn (ViewRecord $record): string => "{$record->viewableType}:{$record->viewableId}");

        $rows = [];

        foreach ($grouped as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();
            $count = $views->pluck('visitor')->unique()->count();

            if ($count >= $minimum) {
                $rows[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'count' => $count];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$b['count'], $a['type'], $a['id']] <=> [$a['count'], $b['type'], $b['id']]);

        return array_slice($rows, 0, $limit);
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
            $this->presence->touch(new Sighting(
                type: $viewable->getMorphClass(),
                key: $key,
                visitor: "fake-visitor-{$visitor}",
                seenAt: Carbon::now(),
                collection: $collection,
            ));
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
     * Reads the recorded views the way the database source reads the views
     * table, without the pairs table.
     *
     * @return RecommendationPairs
     */
    public function recommendationPairs(RecommendationRequest $request, ViewsQuery $query): array
    {
        $recipient = $request->recipient;
        $records = $this->matchingRecords($query, static fn (): bool => true);
        $own = new Collection($this->store->records())->filter(fn (ViewRecord $record): bool => $this->madeBy($record, $recipient));

        $seeds = [];

        foreach ($records->filter(fn (ViewRecord $record): bool => $this->madeBy($record, $recipient)) as $record) {
            $key = self::keyOf($record);
            $viewedAt = $record->viewedAt->toDateTimeString();

            if (! isset($seeds[$key]) || $seeds[$key]['viewed_at'] < $viewedAt) {
                $seeds[$key] = ['type' => $record->viewableType, 'id' => $record->viewableId, 'viewed_at' => $viewedAt];
            }
        }

        $seeds = array_values($seeds);

        usort($seeds, static fn (array $a, array $b): int => [$b['viewed_at'], $a['type'], $a['id']] <=> [$a['viewed_at'], $b['type'], $b['id']]);

        $seeds = array_slice($seeds, 0, $request->seeds);
        $seedKeys = array_map(static fn (array $seed): string => "{$seed['type']}:{$seed['id']}", $seeds);

        $ownVisitors = [$recipient->visitor];

        if ($recipient->isViewer()) {
            $ownVisitors = array_values(array_unique($own->map(static fn (ViewRecord $record): ?string => $record->visitor)->filter(static fn (?string $visitor): bool => $visitor !== null)->all()));
        }

        $seen = array_values(array_unique($own->map(static fn (ViewRecord $record): string => self::keyOf($record))->all()));
        $visited = $records->filter(static fn (ViewRecord $record): bool => $record->visitor !== null);
        $amongType = $request->among?->getMorphClass();
        $pairs = [];

        foreach ($seeds as $seed) {
            $anchors = $this->anchorsOf($visited, "{$seed['type']}:{$seed['id']}", $ownVisitors, $request->maxVisitors);

            $candidates = $visited
                ->filter(static fn (ViewRecord $record): bool => in_array($record->visitor, $anchors, true)
                    && ! in_array(self::keyOf($record), $seedKeys, true)
                    && ($amongType === null || $record->viewableType === $amongType)
                    && ($request->includeSeen || ! in_array(self::keyOf($record), $seen, true)))
                ->groupBy(static fn (ViewRecord $record): string => self::keyOf($record));

            foreach ($candidates as $views) {
                /** @var ViewRecord $first */
                $first = $views->first();
                $count = $views->pluck('visitor')->unique()->count();

                if ($count >= $request->minimum) {
                    $pairs[] = ['seed_type' => $seed['type'], 'seed_id' => $seed['id'], 'type' => $first->viewableType, 'id' => $first->viewableId, 'visitors' => $count];
                }
            }
        }

        if ($pairs === []) {
            return ['seeds' => $seeds, 'pairs' => [], 'audiences' => []];
        }

        usort($pairs, static fn (array $a, array $b): int => [$a['seed_type'], $a['seed_id'], $a['type'], $a['id']] <=> [$b['seed_type'], $b['seed_id'], $b['type'], $b['id']]);

        $involved = [...$seedKeys, ...array_map(static fn (array $pair): string => "{$pair['type']}:{$pair['id']}", $pairs)];
        $audiences = [];

        foreach ($visited->filter(static fn (ViewRecord $record): bool => in_array(self::keyOf($record), $involved, true))->groupBy(static fn (ViewRecord $record): string => self::keyOf($record)) as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();

            $audiences[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'visitors' => $views->pluck('visitor')->unique()->count()];
        }

        return ['seeds' => $seeds, 'pairs' => $pairs, 'audiences' => $audiences];
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
        if ($query->filter instanceof FiltersViews) {
            throw UnsupportedBySource::filter($this);
        }

        $viewerKey = $query->viewer instanceof Model ? (string) ViewerKey::of($query->viewer) : null;

        return new Collection($this->store->records())
            ->filter($filter)
            ->filter(fn (ViewRecord $record): bool => $this->withinPeriod($record, $query->period))
            ->filter(fn (ViewRecord $record): bool => $this->inCollection($record, $query->collection))
            ->filter(fn (ViewRecord $record): bool => $this->byViewer($record, $query->viewer, $viewerKey))
            ->values();
    }

    /**
     * The position of the newest step the view falls in, or an empty string
     * when it is older than every step.
     *
     * @param  list<Step>  $steps
     */
    private function stepOf(ViewRecord $record, array $steps): int|string
    {
        foreach ($steps as $index => $step) {
            if ($record->viewedAt->greaterThanOrEqualTo($step->start)) {
                return $index;
            }
        }

        return '';
    }

    private function withinPeriod(ViewRecord $record, ?Period $period): bool
    {
        $start = $period?->getStartDateTime();

        if ($start instanceof CarbonInterface && $record->viewedAt->lessThan($start)) {
            return false;
        }

        $end = $period?->getEndDateTime();

        if (! $end instanceof CarbonInterface) {
            return true;
        }

        return $record->viewedAt->lessThan($end);
    }

    private function inCollection(ViewRecord $record, ?string $collection): bool
    {
        if ($collection === null) {
            return true;
        }

        return $record->collection === $collection;
    }

    private function byViewer(ViewRecord $record, ?Model $viewer, ?string $viewerKey): bool
    {
        if (! $viewer instanceof Model) {
            return true;
        }

        if ($record->viewerType !== $viewer->getMorphClass()) {
            return false;
        }

        return (string) $record->viewerId === $viewerKey;
    }

    private function madeBy(ViewRecord $record, Recipient $recipient): bool
    {
        if (! $recipient->isViewer()) {
            return $record->visitor === $recipient->visitor;
        }

        if ($record->viewerType !== $recipient->viewer->getMorphClass()) {
            return false;
        }

        return (string) $record->viewerId === (string) $recipient->viewerKey;
    }

    /**
     * The visitors of the seed apart from the recipient's own, the most
     * recent first, capped when a cap is given.
     *
     * @param  Collection<int, ViewRecord>  $visited
     * @param  list<?string>  $ownVisitors
     * @return list<string>
     */
    private function anchorsOf(Collection $visited, string $seed, array $ownVisitors, ?int $maxVisitors): array
    {
        $anchors = $visited
            ->filter(static fn (ViewRecord $record): bool => self::keyOf($record) === $seed && ! in_array($record->visitor, $ownVisitors, true))
            ->groupBy(static fn (ViewRecord $record): string => (string) $record->visitor)
            ->map(static fn (Collection $views, string $visitor): array => [$visitor, (float) $views->max(static fn (ViewRecord $record): float => $record->viewedAt->getPreciseTimestamp())])
            ->values()
            ->all();

        usort($anchors, static fn (array $a, array $b): int => [$b[1], $a[0]] <=> [$a[1], $b[0]]);

        if ($maxVisitors !== null) {
            $anchors = array_slice($anchors, 0, $maxVisitors);
        }

        return array_column($anchors, 0);
    }

    private static function keyOf(ViewRecord $record): string
    {
        return "{$record->viewableType}:{$record->viewableId}";
    }

    /** @param  Collection<int, ViewRecord>  $records */
    private function aggregate(Collection $records, ViewsQuery $query): int
    {
        if (! $query->unique) {
            return $records->count();
        }

        return $records->pluck('visitor')->filter()->unique()->count();
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
