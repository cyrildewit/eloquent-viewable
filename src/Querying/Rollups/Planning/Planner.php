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
 * This planner splits a period between the views table and the rollup tiers.
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
        $start = $this->immutable($start);
        $end = $this->immutable($end);
        $tiers = array_values(array_filter($tiers, static fn (Tier $tier): bool => $state->folded($tier) instanceof CarbonImmutable));
        $rawFloor = $unique ? $this->latest($state->anonymised, $state->pruned) : $state->pruned;
        $rawFrom = $this->rawFrom($state, $tiers, $unique, $rawFloor, $align);

        if (! $rawFrom instanceof CarbonImmutable) {
            return new Plan([new Segment(null, $start, $end)]);
        }

        $historyEnd = $this->earliest($end, $rawFrom);
        $segments = [];

        if ($this->startsBefore($start, $historyEnd)) {
            $segments = $this->cover($state, $tiers, $tiers, $rawFloor, $start, $historyEnd);
        }

        if ($this->endsAfter($end, $rawFrom)) {
            $segments[] = new Segment(null, $this->latest($start, $rawFrom), $end);
        }

        return new Plan($this->merge($segments));
    }

    /**
     * Unique visitors are exact in the views table and summed in the rollups,
     * so for them the views table answers as far back as it is complete. It
     * returns null when everything is read raw.
     *
     * @param  list<Tier>  $tiers
     * @param  (Closure(CarbonImmutable): CarbonImmutable)|null  $align
     */
    private function rawFrom(Snapshot $state, array $tiers, bool $unique, ?CarbonImmutable $rawFloor, ?Closure $align): ?CarbonImmutable
    {
        $from = $this->latest(...array_map($state->folded(...), $tiers));

        if (! $from instanceof CarbonImmutable) {
            return null;
        }

        if ($unique && ! $rawFloor instanceof CarbonImmutable) {
            return null;
        }

        if ($unique) {
            $from = $from->min($rawFloor);
        }

        if (! $align instanceof Closure) {
            return $from;
        }

        $aligned = $align($from);

        if (! $rawFloor instanceof CarbonImmutable) {
            return $aligned;
        }

        if ($aligned < $rawFloor) {
            return $from;
        }

        return $aligned;
    }

    /**
     * @param  list<Tier>  $tiers  the tiers left to try, coarse to fine
     * @param  list<Tier>  $all
     * @return list<Segment>
     */
    private function cover(Snapshot $state, array $tiers, array $all, ?CarbonImmutable $rawFloor, ?CarbonImmutable $from, CarbonImmutable $until): array
    {
        if (! $this->startsBefore($from, $until)) {
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

        if (! $this->startsBefore($first, $last)) {
            return $this->cover($state, $tiers, $all, $rawFloor, $from, $until);
        }

        $before = $first instanceof CarbonImmutable ? $this->cover($state, $tiers, $all, $rawFloor, $from, $first) : [];

        return [
            ...$before,
            new Segment($tier, $first, $last),
            ...$this->cover($state, $tiers, $all, $rawFloor, $last, $until),
        ];
    }

    /**
     * This is what no tier holds whole. The views table answers it exactly
     * while it still holds it; otherwise the finest tier with buckets there
     * does. Nothing was viewed before the first bucket folded, so nothing is
     * read there and a scan of the views table is spared.
     *
     * @param  list<Tier>  $tiers  coarse to fine
     * @return list<Segment>
     */
    private function leftover(Snapshot $state, array $tiers, ?CarbonImmutable $rawFloor, ?CarbonImmutable $from, CarbonImmutable $until): array
    {
        if ($state->origin instanceof CarbonImmutable && $until <= $state->origin) {
            return [];
        }

        $from = $this->latest($from, $state->origin);

        if ($this->viewsTableHolds($rawFloor, $from)) {
            return [new Segment(null, $from, $until)];
        }

        foreach (array_reverse($tiers) as $tier) {
            if ($this->holds($state, $tier, $from, $until)) {
                return [new Segment($tier, $from, $until, exact: false)];
            }
        }

        return [];
    }

    /**
     * A tier holds part of `[from, until)` when its rows start before the end
     * and its last folded bucket ends after the start.
     */
    private function holds(Snapshot $state, Tier $tier, ?CarbonImmutable $from, CarbonImmutable $until): bool
    {
        if (! $this->startsBefore($state->since($tier), $until)) {
            return false;
        }

        if (! $from instanceof CarbonImmutable) {
            return true;
        }

        return $this->endsAfter($state->folded($tier), $from);
    }

    /**
     * The views table still holds every view from `from` on when nothing was
     * deleted from it, or when it was only deleted before `from`.
     */
    private function viewsTableHolds(?CarbonImmutable $rawFloor, ?CarbonImmutable $from): bool
    {
        if (! $rawFloor instanceof CarbonImmutable) {
            return true;
        }

        if (! $from instanceof CarbonImmutable) {
            return false;
        }

        return $from >= $rawFloor;
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

    /**
     * A missing start stands for the beginning of time, so it starts before
     * every moment.
     */
    private function startsBefore(?CarbonImmutable $start, CarbonImmutable $moment): bool
    {
        if (! $start instanceof CarbonImmutable) {
            return true;
        }

        return $start < $moment;
    }

    /**
     * A missing end stands for the end of time, so it ends after every moment.
     */
    private function endsAfter(?CarbonImmutable $end, CarbonImmutable $moment): bool
    {
        if (! $end instanceof CarbonImmutable) {
            return true;
        }

        return $end > $moment;
    }

    private function earliest(?CarbonImmutable $moment, CarbonImmutable $other): CarbonImmutable
    {
        if (! $moment instanceof CarbonImmutable) {
            return $other;
        }

        return $moment->min($other);
    }

    private function latest(?CarbonImmutable ...$moments): ?CarbonImmutable
    {
        $latest = null;

        foreach ($moments as $moment) {
            if (! $moment instanceof CarbonImmutable) {
                continue;
            }

            if ($latest instanceof CarbonImmutable && $moment <= $latest) {
                continue;
            }

            $latest = $moment;
        }

        return $latest;
    }

    private function immutable(?CarbonInterface $moment): ?CarbonImmutable
    {
        if (! $moment instanceof CarbonInterface) {
            return null;
        }

        return CarbonImmutable::instance($moment);
    }
}
