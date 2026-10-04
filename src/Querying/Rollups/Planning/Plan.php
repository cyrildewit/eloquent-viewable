<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use DateTimeZone;

/** @internal */
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

    public function isExact(): bool
    {
        return array_all($this->segments, fn (Segment $segment): bool => $segment->exact);
    }

    /**
     * Unique visitors are exact only when a total is summed from one part: one
     * raw segment or one bucket.
     */
    public function parts(DateTimeZone $zone): int
    {
        $parts = 0;

        foreach ($this->segments as $segment) {
            if (! $segment->tier instanceof Tier) {
                $parts++;

                continue;
            }

            if (! $segment->start instanceof CarbonImmutable) {
                return PHP_INT_MAX;
            }

            if (! $segment->end instanceof CarbonImmutable) {
                return PHP_INT_MAX;
            }

            $parts += $segment->tier->countBetween($segment->start, $segment->end, $zone);
        }

        return $parts;
    }

    /** @return list<CarbonImmutable> */
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
