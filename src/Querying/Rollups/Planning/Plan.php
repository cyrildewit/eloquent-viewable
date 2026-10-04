<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use DateTimeZone;

/**
 * The segments a period is read from, in order.
 *
 * @internal
 */
final readonly class Plan
{
    /** @param  list<Segment>  $segments */
    public function __construct(
        public array $segments,
    ) {}

    public function isRawOnly(): bool
    {
        return $this->rollups() === [];
    }

    /** @return list<Segment> */
    public function raw(): array
    {
        return array_values(array_filter($this->segments, static fn (Segment $segment): bool => $segment->isRaw()));
    }

    /** @return list<Segment> */
    public function rollups(): array
    {
        return array_values(array_filter($this->segments, static fn (Segment $segment): bool => ! $segment->isRaw()));
    }

    /**
     * Whether no segment cuts through a bucket only a rollup still holds.
     */
    public function isExact(): bool
    {
        return array_all($this->segments, fn (Segment $segment): bool => $segment->exact);
    }

    /**
     * How many counts a total is summed from: one per raw segment and one per
     * bucket. Unique visitors are exact only when that is one.
     */
    public function parts(DateTimeZone $zone): int
    {
        $parts = 0;

        foreach ($this->segments as $segment) {
            if (! $segment->tier instanceof Tier) {
                $parts++;
            } elseif (! $segment->start instanceof CarbonImmutable || ! $segment->end instanceof CarbonImmutable) {
                return PHP_INT_MAX;
            } else {
                $parts += $segment->tier->countBetween($segment->start, $segment->end, $zone);
            }
        }

        return $parts;
    }

    /**
     * Where one segment hands over to the next.
     *
     * @return list<CarbonImmutable>
     */
    public function boundaries(): array
    {
        $boundaries = [];

        foreach (array_slice($this->segments, 1) as $segment) {
            if ($segment->start instanceof CarbonImmutable) {
                $boundaries[] = $segment->start;
            }
        }

        return $boundaries;
    }
}
