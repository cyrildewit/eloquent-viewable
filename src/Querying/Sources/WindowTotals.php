<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * It adds up the counts of every viewable per window in one statement, for
 * the sources that implement `CountsByWindow`. Each window is one or more
 * queries of `viewable_type`, `viewable_id` and `aggregate`, the first window
 * the current one and the rest its references.
 *
 * @internal
 */
final readonly class WindowTotals
{
    /**
     * @param  non-empty-list<non-empty-list<Builder>>  $windows
     * @return list<array{type: string, id: int|string, current: int, references: non-empty-list<int>}>
     */
    public static function of(Connection $connection, array $windows, int $minimum): array
    {
        $union = null;

        foreach ($windows as $slot => $branches) {
            foreach ($branches as $index => $branch) {
                $slotted = $connection->query()
                    ->fromSub($branch, "window_{$slot}_{$index}")
                    ->selectRaw("viewable_type, viewable_id, aggregate, {$slot} as slot"); // @phpstan-ignore argument.type (an integer, not user input)

                if (! $union instanceof Builder) {
                    $union = $slotted;

                    continue;
                }

                $union->unionAll($slotted);
            }
        }

        $samples = count($windows) - 1;
        $columns = array_map(fn (int $slot): string => "sum(case when slot = {$slot} then aggregate else 0 end) as window_{$slot}", array_keys($windows));
        $inCurrent = 'sum(case when slot = 0 then aggregate else 0 end)';
        $inReferences = 'sum(case when slot > 0 then aggregate else 0 end)';

        $rows = $connection->query()
            ->fromSub($union, 'counted')
            ->selectRaw('viewable_type, viewable_id, '.implode(', ', $columns)) // @phpstan-ignore argument.type (integers, not user input)
            ->groupBy('viewable_type', 'viewable_id')
            ->havingRaw("{$inCurrent} >= ? or {$inReferences} >= ?", [$minimum, $minimum * $samples])
            ->get();

        $counts = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string} $row */
        foreach ($rows as $row) {
            /** @var non-empty-list<int> $references */
            $references = array_map(fn (int $slot): int => (int) $row->{"window_{$slot}"}, range(1, count($windows) - 1)); // @phpstan-ignore cast.int (a sum)

            $counts[] = [
                'type' => $row->viewable_type,
                'id' => $row->viewable_id,
                'current' => (int) $row->window_0, // @phpstan-ignore cast.int (a sum)
                'references' => $references,
            ];
        }

        return $counts;
    }
}
