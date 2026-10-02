<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ListingStats;

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
        public int $previousViews,
    ) {}

    public function totalViews(): int
    {
        return $this->views->total();
    }

    /**
     * The change in views against the previous window as a whole percentage,
     * or null when the previous window has no views to compare with.
     */
    public function change(): ?int
    {
        if ($this->previousViews === 0) {
            return null;
        }

        return (int) round(($this->totalViews() - $this->previousViews) / $this->previousViews * 100);
    }
}
