<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ListingStats;

use Carbon\Carbon;
use CyrildeWit\EloquentViewable\Support\Granularity;
use CyrildeWit\EloquentViewable\Support\Period;

class ListingStats
{
    /**
     * The window ends with today, so it covers today so far and the 29 days
     * before it.
     */
    private const int WINDOW_DAYS = 30;

    /**
     * How stale the current window may be. Its last bucket is today, which
     * keeps changing while the listing is viewed.
     */
    private const int CACHE_MINUTES = 10;

    public function for(Listing $listing): ListingReport
    {
        // Both windows start at midnight. A period's cache key is built from
        // its timestamps, so a start that moves with the clock would give
        // every call a new key and nothing would ever be read from the cache.
        $today = Carbon::today();
        $current = Period::since($today->copy()->subDays(self::WINDOW_DAYS - 1));
        $previous = Period::create(
            $today->copy()->subDays(self::WINDOW_DAYS * 2 - 1),
            $today->copy()->subDays(self::WINDOW_DAYS - 1),
        );

        return new ListingReport(
            views: views($listing)
                ->period($current)
                ->remember(self::CACHE_MINUTES)
                ->countByInterval(Granularity::Day),
            visitorsPerDay: views($listing)
                ->period($current)
                ->unique()
                ->remember(self::CACHE_MINUTES)
                ->countByInterval(Granularity::Day),
            visitors: views($listing)
                ->period($current)
                ->unique()
                ->remember(self::CACHE_MINUTES)
                ->count(),
            // Views are recorded at the current time, so a window that has
            // ended cannot change. It is cached until its key goes out of use
            // at midnight.
            previousViews: views($listing)
                ->period($previous)
                ->remember(Carbon::tomorrow())
                ->count(),
        );
    }
}
