<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Data;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Support\Timezone;
use DateTimeImmutable;

/**
 * Reads the stored `viewed_at` wall clock as `from` and turns it into the
 * wall clock of `to`, over the instants between `start` and `end`.
 *
 * Between two daylight saving transitions of either zone the difference
 * between the two clocks is constant, so the range splits into a handful of
 * segments, each with one fixed offset. A stored wall clock that occurs
 * twice, during the hour a fall-back transition of `from` repeats, is read
 * as its later occurrence, the same choice PHP makes when it parses that
 * wall clock.
 */
final readonly class TimezoneConversion
{
    private const string WallClock = 'Y-m-d H:i:s';

    public function __construct(
        public Timezone $from,
        public Timezone $to,
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    public function isNoop(): bool
    {
        return array_all($this->segments(), fn (OffsetSegment $segment): bool => $segment->offset === 0);
    }

    /**
     * The offsets to apply, in stored wall-clock order. The first segment has
     * no lower bound; every later one starts where a transition of either
     * zone changes the difference between the clocks. Consecutive segments
     * with the same offset are merged.
     *
     * @return non-empty-list<OffsetSegment>
     */
    public function segments(): array
    {
        $begin = $this->start->getTimestamp();
        $until = $this->end->getTimestamp();

        $instants = [];

        foreach ([$this->from, $this->to] as $zone) {
            // The first entry describes the state at $begin, not a transition.
            foreach ($zone->getTransitions($begin, $until) as $transition) {
                if ($transition['ts'] > $begin) {
                    $instants[$transition['ts']] = true;
                }
            }
        }

        ksort($instants);

        $segments = [new OffsetSegment(null, $this->offsetAt($begin))];

        foreach (array_keys($instants) as $instant) {
            $offset = $this->offsetAt($instant);

            if ($offset === array_last($segments)->offset) {
                continue;
            }

            $segments[] = new OffsetSegment($this->storedWallClockAt($instant), $offset);
        }

        return $segments;
    }

    private function offsetAt(int $instant): int
    {
        $at = new DateTimeImmutable("@{$instant}");

        return $this->to->getOffset($at) - $this->from->getOffset($at);
    }

    private function storedWallClockAt(int $instant): string
    {
        return new DateTimeImmutable("@{$instant}")->setTimezone($this->from)->format(self::WallClock);
    }
}
