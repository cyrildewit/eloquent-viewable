<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use CyrildeWit\EloquentViewable\Querying\Rollups\Snapshot;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use DateTimeZone;

/**
 * Splits a period between the views table and the rollup tiers.
 *
 * Views after the last folded bucket are always read raw, and so are unique
 * visitors back to where the views table stops being exact. Older history is
 * read from the coarsest tier that holds whole buckets of it, its edges from
 * finer tiers, and what no tier holds whole from the views table while it
 * still has it. An edge that is left goes to the finest tier that holds it,
 * counted by the buckets that start inside it, which is inexact.
 *
 * @internal
 */
final readonly class Planner
{
    public function __construct(
        private DateTimeZone $zone,
    ) {}

    /**
     * @param  list<Tier>  $tiers  the tiers that may answer, coarse to fine
     * @param  (Closure(CarbonImmutable): CarbonImmutable)|null  $align  moves the hand-over to raw back, onto the edge of a series bucket
     */
    public function plan(Snapshot $state, array $tiers, ?CarbonInterface $start, ?CarbonInterface $end, bool $unique, ?Closure $align = null): Plan
    {
        $start = $start instanceof CarbonInterface ? CarbonImmutable::instance($start) : null;
        $end = $end instanceof CarbonInterface ? CarbonImmutable::instance($end) : null;
        $tiers = array_values(array_filter($tiers, static fn (Tier $tier): bool => $state->folded($tier) instanceof CarbonImmutable));
        $rawFloor = $unique ? $this->latest($state->anonymised, $state->pruned) : $state->pruned;
        $rawFrom = $this->rawFrom($state, $tiers, $unique, $rawFloor, $align);

        if (! $rawFrom instanceof CarbonImmutable) {
            return new Plan([new Segment(null, $start, $end)]);
        }

        $historyEnd = $end instanceof CarbonImmutable && $end < $rawFrom ? $end : $rawFrom;
        $segments = [];

        if (! $start instanceof CarbonImmutable || $start < $historyEnd) {
            $segments = $this->cover($state, $tiers, $tiers, $rawFloor, $start, $historyEnd);
        }

        if (! $end instanceof CarbonImmutable || $end > $rawFrom) {
            $segments[] = new Segment(null, $start instanceof CarbonImmutable && $start > $rawFrom ? $start : $rawFrom, $end);
        }

        return new Plan($this->merge($segments));
    }

    /**
     * Null when everything is read raw.
     *
     * @param  list<Tier>  $tiers
     * @param  (Closure(CarbonImmutable): CarbonImmutable)|null  $align
     */
    private function rawFrom(Snapshot $state, array $tiers, bool $unique, ?CarbonImmutable $rawFloor, ?Closure $align): ?CarbonImmutable
    {
        $from = null;

        foreach ($tiers as $tier) {
            $from = $this->latest($from, $state->folded($tier));
        }

        if (! $from instanceof CarbonImmutable) {
            return null;
        }

        // Unique visitors are exact in the views table and summed in the
        // rollups, so the views table answers as far back as it is complete.
        if ($unique) {
            if (! $rawFloor instanceof CarbonImmutable) {
                return null;
            }

            $from = $from->min($rawFloor);
        }

        if ($align instanceof Closure) {
            $aligned = $align($from);

            if (! $rawFloor instanceof CarbonImmutable || $aligned >= $rawFloor) {
                return $aligned;
            }
        }

        return $from;
    }

    /**
     * @param  list<Tier>  $tiers  the tiers left to try, coarse to fine
     * @param  list<Tier>  $all
     * @return list<Segment>
     */
    private function cover(Snapshot $state, array $tiers, array $all, ?CarbonImmutable $rawFloor, ?CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($from instanceof CarbonImmutable && $from >= $until) {
            return [];
        }

        $tier = array_shift($tiers);

        if (! $tier instanceof Tier) {
            return $this->leftover($state, $all, $rawFloor, $from, $until);
        }

        $low = $this->latest($from, $state->since($tier));
        $high = $until->min($state->folded($tier) ?? $until);
        $first = $low instanceof CarbonImmutable ? $tier->ceil($low, $this->zone) : null;
        $last = $tier->floor($high, $this->zone);

        if ($first instanceof CarbonImmutable && $first >= $last) {
            return $this->cover($state, $tiers, $all, $rawFloor, $from, $until);
        }

        return [
            ...($first instanceof CarbonImmutable ? $this->cover($state, $tiers, $all, $rawFloor, $from, $first) : []),
            new Segment($tier, $first, $last),
            ...$this->cover($state, $tiers, $all, $rawFloor, $last, $until),
        ];
    }

    /**
     * What no tier holds whole. The views table answers it exactly while it
     * still holds it; otherwise the finest tier with buckets there does.
     *
     * @param  list<Tier>  $tiers  coarse to fine
     * @return list<Segment>
     */
    private function leftover(Snapshot $state, array $tiers, ?CarbonImmutable $rawFloor, ?CarbonImmutable $from, CarbonImmutable $until): array
    {
        // Nothing was viewed before the first bucket folded, so there is
        // nothing to read there, and a scan of the views table is spared.
        if ($state->origin instanceof CarbonImmutable) {
            if ($until <= $state->origin) {
                return [];
            }

            $from = $this->latest($from, $state->origin);
        }

        if (! $rawFloor instanceof CarbonImmutable || ($from instanceof CarbonImmutable && $from >= $rawFloor)) {
            return [new Segment(null, $from, $until)];
        }

        foreach (array_reverse($tiers) as $tier) {
            $since = $state->since($tier);
            $folded = $state->folded($tier);

            if ((! $since instanceof CarbonImmutable || $since < $until) && (! $from instanceof CarbonImmutable || $folded > $from)) {
                return [new Segment($tier, $from, $until, exact: false)];
            }
        }

        return [];
    }

    /**
     * @param  list<Segment>  $segments
     * @return list<Segment>
     */
    private function merge(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $previous = array_pop($merged);

            if ($previous instanceof Segment && $previous->isRaw() && $segment->isRaw()) {
                $merged[] = new Segment(null, $previous->start, $segment->end);

                continue;
            }

            if ($previous instanceof Segment) {
                $merged[] = $previous;
            }

            $merged[] = $segment;
        }

        return $merged;
    }

    private function latest(?CarbonImmutable ...$moments): ?CarbonImmutable
    {
        $latest = null;

        foreach ($moments as $moment) {
            if ($moment instanceof CarbonImmutable && (! $latest instanceof CarbonImmutable || $moment > $latest)) {
                $latest = $moment;
            }
        }

        return $latest;
    }
}
