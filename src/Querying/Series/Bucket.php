<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Series;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Support\Period;

/**
 * The number of views inside one bucket of a series. The bucket is half-open,
 * `[start, end)`: `end` is the start of the next bucket and is excluded.
 * The label comes from the wall clock, so it stays unique where a DST
 * transition shifts `start`.
 */
final readonly class Bucket
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
        public int $count,
        public string $label,
    ) {}

    public function period(): Period
    {
        return Period::create($this->start, $this->end);
    }
}
