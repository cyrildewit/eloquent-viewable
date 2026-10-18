<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Contracts\FiltersViews;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Data\ViewRecord;
use CyrildeWit\EloquentViewable\Dimensions\DimensionDefinition;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Exceptions\UnsupportedBySource;
use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use CyrildeWit\EloquentViewable\Querying\Ranking\Step;
use CyrildeWit\EloquentViewable\Querying\Recommendations\Recipient;
use CyrildeWit\EloquentViewable\Querying\Recommendations\RecommendationRequest;
use CyrildeWit\EloquentViewable\Support\AnonymisedVisitor;
use CyrildeWit\EloquentViewable\Support\DimensionFilter;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\Timezone;
use CyrildeWit\EloquentViewable\Support\ViewerKey;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Counts view records held in memory the way the database source counts
 * the views table, so a test runs the same reads without a database. It
 * cannot apply the filter of a custom rollup.
 *
 * @phpstan-import-type RecommendationPairs from RanksRecommendations
 */
final readonly class ArraySource implements CountsBy, CountsByWindow, CountsVisitFrequency, RanksAlsoViewed, RanksRecommendations, RanksTrending, ViewSource
{
    /** @param  Closure(): list<ViewRecord>  $records  the records to count, read on every call */
    public function __construct(
        private Closure $records,
    ) {}

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
     * Counts the recorded views the way the database source counts the views
     * table: the values past the limit are counted again, so unique visitors
     * are exact there too.
     */
    public function countBy(Viewable $viewable, ViewsQuery $query, DimensionDefinition $dimension, ?int $limit = null): DimensionCounts
    {
        $keys = $dimension->storage()->keys();
        $valueOf = fn (ViewRecord $record): ?string => $this->valueOf($record, $dimension->name, $keys);

        $records = $this->matching($viewable, $query);
        $valued = $records->filter(static fn (ViewRecord $record): bool => $valueOf($record) !== null);

        /** @var array<string, int> $counts */
        $counts = $valued
            ->groupBy(static fn (ViewRecord $record): string => (string) $valueOf($record))
            ->map(fn (Collection $views): int => $this->aggregate($views, $query))
            ->all();

        $kept = DimensionCounts::from($counts)->limit($limit)->all();
        $rest = $valued->reject(static fn (ViewRecord $record): bool => array_key_exists((string) $valueOf($record), $kept));

        return DimensionCounts::from(
            $kept,
            $this->aggregate($records->filter(static fn (ViewRecord $record): bool => $valueOf($record) === null), $query),
            $this->aggregate($rest, $query),
            $this->aggregate($records, $query),
        );
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
            ->groupBy(self::keyOf(...));

        foreach ($grouped as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();

            $rows[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'count' => $this->aggregate($views, $query)];
        }

        return $this->ranked($rows, static fn (array $row): int => $row['count'], $limit);
    }

    /**
     * Counts the recorded views per window, as the database source does in SQL.
     *
     * @param  non-empty-list<Period>  $references
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     */
    public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array
    {
        $type = $viewable?->getMorphClass();
        $windows = [$query, ...array_map($query->withPeriod(...), $references)];
        $counts = [];

        foreach ($windows as $slot => $window) {
            $grouped = $this->matchingRecords($window, fn (ViewRecord $record): bool => $type === null || $record->viewableType === $type)
                ->groupBy(self::keyOf(...));

            foreach ($grouped as $key => $views) {
                /** @var ViewRecord $first */
                $first = $views->first();

                $counts[$key] ??= ['type' => $first->viewableType, 'id' => $first->viewableId, 'counts' => array_fill(0, count($windows), 0)];
                $counts[$key]['counts'][$slot] = $this->aggregate($views, $window);
            }
        }

        $rows = [];

        foreach ($counts as $count) {
            /** @var non-empty-list<int> $references */
            $references = array_slice($count['counts'], 1);
            $current = $count['counts'][0];
            $reaches = $current >= $minimum || array_sum($references) >= $minimum * count($references);

            if (! $reaches) {
                continue;
            }

            $rows[] = ['type' => $count['type'], 'id' => $count['id'], 'current' => $current, 'references' => $references];
        }

        return $rows;
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
            ->groupBy(self::keyOf(...));

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

        return array_map(static fn (array $row): array => [...$row, 'score' => $row['score'] / Decay::Scale], $this->ranked($rows, static fn (array $row): int => $row['score'], $limit));
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
            ->groupBy(self::keyOf(...));

        $rows = [];

        foreach ($grouped as $views) {
            /** @var ViewRecord $first */
            $first = $views->first();
            $count = $views->pluck('visitor')->unique()->count();

            if ($count >= $minimum) {
                $rows[] = ['type' => $first->viewableType, 'id' => $first->viewableId, 'count' => $count];
            }
        }

        return $this->ranked($rows, static fn (array $row): int => $row['count'], $limit);
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
        $own = new Collection(($this->records)())->filter(fn (ViewRecord $record): bool => $this->madeBy($record, $recipient));

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

        return new Collection(($this->records)())
            ->filter($filter)
            ->filter(fn (ViewRecord $record): bool => $this->withinPeriod($record, $query->period))
            ->filter(fn (ViewRecord $record): bool => $this->inCollection($record, $query->collection))
            ->filter(fn (ViewRecord $record): bool => $this->byViewer($record, $query->viewer, $viewerKey))
            ->filter(fn (ViewRecord $record): bool => $this->withDimensions($record, $query->dimensions))
            ->values();
    }

    /** @param  list<DimensionFilter>  $filters */
    private function withDimensions(ViewRecord $record, array $filters): bool
    {
        return array_all($filters, fn (DimensionFilter $filter): bool => in_array($this->valueOf($record, $filter->name, $filter->keys()), $filter->values, true));
    }

    /**
     * The value of a dimension on a recorded view: its column, or the path
     * into the context when it is kept there.
     *
     * @param  list<string>  $keys
     */
    private function valueOf(ViewRecord $record, string $name, array $keys): ?string
    {
        $value = $keys === []
            ? $record->dimensions[$name] ?? null
            : data_get($record->context, implode('.', $keys));

        return is_string($value) ? $value : null;
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

    /**
     * The rows with the highest value first, ties by type and key, cut to
     * the limit.
     *
     * @template TRow of array{type: string, id: int|string}
     *
     * @param  list<TRow>  $rows
     * @param  Closure(TRow): (int|float)  $value
     * @return list<TRow>
     */
    private function ranked(array $rows, Closure $value, int $limit): array
    {
        usort($rows, static fn (array $a, array $b): int => [$value($b), $a['type'], $a['id']] <=> [$value($a), $b['type'], $b['id']]);

        return array_slice($rows, 0, $limit);
    }

    /** @param  Collection<int, ViewRecord>  $records */
    private function aggregate(Collection $records, ViewsQuery $query): int
    {
        if (! $query->unique) {
            return $records->count();
        }

        return $records->pluck('visitor')->filter()->unique()->count();
    }
}
