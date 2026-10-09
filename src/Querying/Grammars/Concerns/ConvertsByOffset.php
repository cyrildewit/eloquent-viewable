<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Grammars\Concerns;

use CyrildeWit\EloquentViewable\Querying\Data\TimezoneConversion;

/**
 * Converts between zones by shifting the stored wall clock by a fixed
 * number of seconds per stretch between daylight saving transitions, picked
 * by comparing the column against the transition's wall clock. PHP computes
 * the segments, so the database needs no zone tables and the labels agree
 * with the series by construction. The thresholds are formatted by the
 * package, not taken from input.
 */
trait ConvertsByOffset
{
    public function convertTimezone(string $column, TimezoneConversion $conversion): string
    {
        $segments = $conversion->segments();

        if (count($segments) === 1) {
            return $this->shiftUnlessZero($column, $segments[0]->offset);
        }

        $sql = 'case';
        $previous = array_shift($segments);

        foreach ($segments as $segment) {
            $sql .= " when {$column} < '{$segment->startsAt}' then {$this->shiftUnlessZero($column, $previous->offset)}";
            $previous = $segment;
        }

        return "({$sql} else {$this->shiftUnlessZero($column, $previous->offset)} end)";
    }

    /**
     * SQL that adds a signed number of seconds to an already-wrapped column.
     */
    abstract protected function shift(string $column, int $seconds): string;

    private function shiftUnlessZero(string $column, int $seconds): string
    {
        return $seconds === 0 ? $column : $this->shift($column, $seconds);
    }
}
