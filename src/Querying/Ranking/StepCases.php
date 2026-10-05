<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Carbon\CarbonImmutable;

/**
 * The `case` expressions that sort a view into the steps of a decay. The step
 * starts are bound and the weights written as integer literals, because
 * Postgres cannot always infer the type of a parameter inside a `case`, and
 * float sums differ per driver.
 *
 * @internal
 */
final readonly class StepCases
{
    /**
     * The weight of the step a moment falls in, 0 outside every step.
     *
     * @return array{string, list<CarbonImmutable>}
     */
    public static function weight(Decay $decay, string $column): array
    {
        return self::sort($decay, $column, static fn (Step $step, int $index): int => $step->weight, '0');
    }

    /**
     * The position of the step a moment falls in, null outside every step,
     * for grouping by step.
     *
     * @return array{string, list<CarbonImmutable>}
     */
    public static function index(Decay $decay, string $column): array
    {
        return self::sort($decay, $column, static fn (Step $step, int $index): int => $index, 'null');
    }

    /**
     * The weight of the step at a position that `index()` produced.
     */
    public static function weightOfIndex(Decay $decay, string $column): string
    {
        $steps = $decay->steps();

        if ($steps === []) {
            return '0';
        }

        $cases = '';

        foreach ($steps as $index => $step) {
            $cases .= " when {$index} then {$step->weight}";
        }

        return "case {$column}{$cases} else 0 end";
    }

    /**
     * The steps are searched as a balanced tree of nested `case` expressions
     * rather than one after another, so a view is compared with the starts
     * of a few steps instead of every step newer than its own. The SQL for
     * a year of days stays fast on every driver.
     *
     * @param  callable(Step, int): int  $value
     * @return array{string, list<CarbonImmutable>}
     */
    private static function sort(Decay $decay, string $column, callable $value, string $otherwise): array
    {
        $steps = $decay->steps();

        if ($steps === []) {
            return ['0', []];
        }

        [$found, $bindings] = self::search($steps, 0, $column, $value);

        return ["case when {$column} >= ? then {$found} else {$otherwise} end", [array_last($steps)->start, ...$bindings]];
    }

    /**
     * The value of the newest of the steps that the moment is not before,
     * knowing it is not before the start of the oldest. The offset is the
     * position of the first of them among all steps.
     *
     * @param  non-empty-list<Step>  $steps
     * @param  callable(Step, int): int  $value
     * @return array{string, list<CarbonImmutable>}
     */
    private static function search(array $steps, int $offset, string $column, callable $value): array
    {
        $count = count($steps);

        if ($count === 1) {
            return [(string) $value($steps[0], $offset), []];
        }

        $half = intdiv($count + 1, 2);

        /** @var non-empty-list<Step> $newer */
        $newer = array_slice($steps, 0, $half);

        /** @var non-empty-list<Step> $older */
        $older = array_slice($steps, $half);

        [$inNewer, $newerBindings] = self::search($newer, $offset, $column, $value);
        [$inOlder, $olderBindings] = self::search($older, $offset + $half, $column, $value);

        return ["case when {$column} >= ? then {$inNewer} else {$inOlder} end", [array_last($newer)->start, ...$newerBindings, ...$olderBindings]];
    }
}
