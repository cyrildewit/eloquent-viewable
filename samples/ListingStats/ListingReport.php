<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ListingStats;

use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Querying\Dimensions\DimensionCounts;
use CyrildeWit\EloquentViewable\Querying\Series\ViewSeries;

final readonly class ListingReport
{
    public function __construct(
        /** Views per day, oldest first, with today as the last bucket. */
        public ViewSeries $views,
        /** Distinct visitors per day. A visitor who returns is counted again on each day. */
        public ViewSeries $visitorsPerDay,
        /** Distinct visitors over the whole window, which is less than the sum of the days. */
        public int $visitors,
        /** The views of the window against the 30 days before it. */
        public ViewComparison $trend,
        /** The views per source, the five largest by name and the rest in `other()`. */
        public DimensionCounts $sources,
    ) {}

    public function totalViews(): int
    {
        return $this->views->total();
    }
}
