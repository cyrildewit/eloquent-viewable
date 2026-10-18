<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Sources;

use CyrildeWit\EloquentViewable\Querying\Ranking\Decay;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Turns the rows the sources read into the arrays the contracts promise.
 * The database drivers return the aggregates as integers or as strings, so
 * every value is cast.
 *
 * @internal
 */
final class Rows
{
    /**
     * @param  iterable<int|string, int|string>  $rows  an aggregate per key
     * @return array<string, int>
     */
    public static function ints(iterable $rows): array
    {
        $counts = [];

        foreach ($rows as $key => $count) {
            $counts[(string) $key] = (int) $count;
        }

        return $counts;
    }

    /**
     * @param  iterable<int, mixed>  $rows  `viewable_type`, `viewable_id` and `aggregate`
     * @return list<array{type: string, id: int|string, count: int}>
     */
    public static function ranked(iterable $rows): array
    {
        $ranking = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, aggregate: int|string} $row */
        foreach ($rows as $row) {
            $ranking[] = ['type' => $row->viewable_type, 'id' => $row->viewable_id, 'count' => (int) $row->aggregate];
        }

        return $ranking;
    }

    /**
     * The rows of a trending ranking, the integer score scaled back.
     *
     * @param  iterable<int, mixed>  $rows  `viewable_type`, `viewable_id`, `aggregate` and `score`
     * @return list<array{type: string, id: int|string, count: int, score: float}>
     */
    public static function scored(iterable $rows): array
    {
        $ranking = [];

        /** @var stdClass&object{viewable_type: string, viewable_id: int|string, aggregate: int|string, score: int|string|float} $row */
        foreach ($rows as $row) {
            $ranking[] = [
                'type' => $row->viewable_type,
                'id' => $row->viewable_id,
                'count' => (int) $row->aggregate,
                'score' => (float) $row->score / Decay::Scale,
            ];
        }

        return $ranking;
    }

    /**
     * One statement that reads every branch, each selecting the same columns.
     *
     * @param  non-empty-list<Builder>  $branches
     */
    public static function union(array $branches): Builder
    {
        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return $union;
    }
}
