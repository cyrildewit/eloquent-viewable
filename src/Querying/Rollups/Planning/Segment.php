<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Planning;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Querying\Rollups\Tier;
use CyrildeWit\EloquentViewable\Support\Period;

/**
 * A stretch of a period and where it is read from: the views table when the
 * tier is null, or the buckets of a tier that start inside it. Inexact when
 * its edges cut through a bucket only a rollup still holds.
 *
 * @internal
 */
final readonly class Segment
{
    public function __construct(
        public ?Tier $tier,
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
        public bool $exact = true,
    ) {}

    public function isRaw(): bool
    {
        return ! $this->tier instanceof Tier;
    }

    /** @throws InvalidPeriod */
    public function period(): ?Period
    {
        if (! $this->start instanceof CarbonImmutable && ! $this->end instanceof CarbonImmutable) {
            return null;
        }

        return Period::create($this->start, $this->end);
    }
}
