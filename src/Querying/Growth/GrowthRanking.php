<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Growth;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidBaseline;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/**
 * It ranks the viewables of a type by how their count in the period of the
 * query compares with a reference: the period before it for `rising()`, and
 * the same period on past days or weeks for `anomalies()`. The source counts
 * every window in one go, and the scores are worked out here, so every source
 * scores the same way.
 *
 * @internal
 *
 * @phpstan-type GrowthRow array{type: string, id: int|string, count: int, score: float, baseline: Baseline}
 */
final readonly class GrowthRanking
{
    /**
     * Rank by the count over the count of the period before, highest first.
     * Only viewables that grew are ranked.
     *
     * @return list<GrowthRow>
     *
     * @throws InvalidPeriod
     */
    public function rising(CountsByWindow $source, ?Viewable $viewable, ViewsQuery $query, int $minimum, int $limit): array
    {
        $period = $query->period ?? throw InvalidPeriod::comparedWithoutPeriod();

        $rows = array_filter(
            $this->rows($source->countByWindow($viewable, $query, [$period->previous()], $minimum), fn (Baseline $baseline): float => $baseline->ratio()),
            fn (array $row): bool => $row['baseline']->current > $row['baseline']->mean,
        );

        return $this->ranked($rows, descending: true, limit: $limit);
    }

    /**
     * Rank by how many deviations the count lies from the same period on
     * past days or weeks. A positive threshold ranks the viewables at or
     * above it, highest first; a negative one those at or below it, lowest
     * first.
     *
     * @return list<GrowthRow>
     *
     * @throws InvalidBaseline
     * @throws InvalidPeriod
     */
    public function anomalies(CountsByWindow $source, ?Viewable $viewable, ViewsQuery $query, Seasonality $seasonality, int $samples, float $threshold, int $minimum, int $limit): array
    {
        if ($threshold === 0.0) {
            throw InvalidBaseline::thresholdOfZero();
        }

        $period = $query->period ?? throw InvalidBaseline::withoutStart();
        $references = $seasonality->references($period, $samples);

        $rows = array_filter(
            $this->rows($source->countByWindow($viewable, $query, $references, $minimum), fn (Baseline $baseline): float => $baseline->zScore()),
            fn (array $row): bool => $threshold > 0 ? $row['score'] >= $threshold : $row['score'] <= $threshold,
        );

        return $this->ranked($rows, descending: $threshold > 0, limit: $limit);
    }

    /**
     * @param  list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>  $counts
     * @param  callable(Baseline): float  $score
     * @return list<GrowthRow>
     */
    private function rows(array $counts, callable $score): array
    {
        $rows = [];

        foreach ($counts as $count) {
            $baseline = new Baseline($count['current'], $count['references']);

            $rows[] = [
                'type' => $count['type'],
                'id' => $count['id'],
                'count' => $count['current'],
                'score' => $score($baseline),
                'baseline' => $baseline,
            ];
        }

        return $rows;
    }

    /**
     * Sort by score, then by count, type and key, so the order is the same on
     * every source.
     *
     * @param  array<int, GrowthRow>  $rows
     * @return list<GrowthRow>
     */
    private function ranked(array $rows, bool $descending, int $limit): array
    {
        usort($rows, function (array $a, array $b) use ($descending): int {
            $score = $descending
                ? $b['score'] <=> $a['score']
                : $a['score'] <=> $b['score'];

            if ($score !== 0) {
                return $score;
            }

            return [$b['count'], $a['type'], $a['id']] <=> [$a['count'], $b['type'], $b['id']];
        });

        return array_slice($rows, 0, $limit);
    }
}
