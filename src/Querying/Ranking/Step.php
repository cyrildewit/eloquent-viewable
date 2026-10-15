<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Carbon\CarbonImmutable;

/**
 * A step of a trending window: every view from its start up to the start of
 * the next, newer step weighs the same. The weight is the curve's weight
 * times 1,000,000, so sums stay integers on every driver.
 */
final readonly class Step
{
    public function __construct(
        public CarbonImmutable $start,
        public int $weight,
    ) {}
}
