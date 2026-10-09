<?php

declare(strict_types=1);

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Growth\GrowthRanking;
use CyrildeWit\EloquentViewable\Querying\Growth\Seasonality;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Support\ViewsQuery;

/** @param  list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>  $rows */
function windowCounts(array $rows): CountsByWindow
{
    return new class($rows) implements CountsByWindow
    {
        /** @var list<Period> */
        public array $references = [];

        /** @param  list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>  $rows */
        public function __construct(private readonly array $rows) {}

        public function countByWindow(?Viewable $viewable, ViewsQuery $query, array $references, int $minimum): array
        {
            $this->references = $references;

            return $this->rows;
        }
    };
}

/**
 * @param  list<array{type: string, id: int|string, count: int, score: float}>  $rows
 * @return list<array{string, int|string, float}>
 */
function growthScores(array $rows): array
{
    return array_map(fn (array $row): array => [$row['type'], $row['id'], round($row['score'], 2)], $rows);
}

it('ranks what grew by its ratio, then by count, type and key', function (): void {
    $source = windowCounts([
        ['type' => 'posts', 'id' => 2, 'current' => 20, 'references' => [10]],
        ['type' => 'posts', 'id' => 1, 'current' => 40, 'references' => [20]],
        ['type' => 'pages', 'id' => 3, 'current' => 40, 'references' => [20]],
        ['type' => 'posts', 'id' => 4, 'current' => 30, 'references' => [5]],
        ['type' => 'posts', 'id' => 5, 'current' => 5, 'references' => [30]],
        ['type' => 'posts', 'id' => 6, 'current' => 9, 'references' => [9]],
    ]);

    $rows = new GrowthRanking()->rising($source, null, new ViewsQuery(Period::create('2026-10-08 10:00', '2026-10-08 12:00')), 1, 4);

    expect(growthScores($rows))->toBe([['posts', 4, 6.0], ['pages', 3, 2.0], ['posts', 1, 2.0], ['posts', 2, 2.0]])
        ->and($source->references[0]->getStartDateTime()?->format('Y-m-d H:i'))->toBe('2026-10-08 08:00');
});

it('ranks spikes at or above the threshold, highest first', function (): void {
    $source = windowCounts([
        ['type' => 'posts', 'id' => 1, 'current' => 30, 'references' => [10, 10]],
        ['type' => 'posts', 'id' => 2, 'current' => 60, 'references' => [10, 10]],
        ['type' => 'posts', 'id' => 3, 'current' => 13, 'references' => [10, 10]],
        ['type' => 'posts', 'id' => 4, 'current' => 0, 'references' => [10, 10]],
    ]);

    $rows = new GrowthRanking()->anomalies($source, null, new ViewsQuery(Period::create('2026-10-08 11:00', '2026-10-08 12:00')), Seasonality::Week, 2, 3.0, 1, 10);

    expect(growthScores($rows))->toBe([['posts', 2, 15.81], ['posts', 1, 6.32]])
        ->and(array_map(fn (Period $period): ?string => $period->getStartDateTime()?->format('Y-m-d'), $source->references))->toBe(['2026-10-01', '2026-09-24']);
});

it('ranks drops at or below a negative threshold, lowest first', function (): void {
    $source = windowCounts([
        ['type' => 'posts', 'id' => 1, 'current' => 0, 'references' => [10, 10]],
        ['type' => 'posts', 'id' => 2, 'current' => 0, 'references' => [40, 40]],
        ['type' => 'posts', 'id' => 3, 'current' => 9, 'references' => [10, 10]],
    ]);

    $rows = new GrowthRanking()->anomalies($source, null, new ViewsQuery(Period::create('2026-10-08 11:00', '2026-10-08 12:00')), Seasonality::Day, 2, -3.0, 1, 1);

    expect(growthScores($rows))->toBe([['posts', 2, -6.32]]);
});
