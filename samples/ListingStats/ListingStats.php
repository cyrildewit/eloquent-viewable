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
    private const int WindowDays = 30;

    /**
     * How stale the counts may be. The last bucket is today, which keeps
     * changing while the listing is viewed.
     */
    private const int CacheMinutes = 10;

    /**
     * The sources listed by name. The views of the rest are added up as one
     * row, so a long tail of referring sites does not push the page down.
     */
    private const int TopSources = 5;

    public function for(Listing $listing): ListingReport
    {
        // The window runs from midnight to midnight. A period's cache key is
        // built from its timestamps, so a bound that moves with the clock
        // would give every call a new key and nothing would ever be read
        // from the cache. Both bounds are needed for `compare()`, which steps
        // back by the width of the period.
        $today = Carbon::today();
        $window = Period::create($today->copy()->subDays(self::WindowDays - 1), $today->copy()->addDay());

        return new ListingReport(
            views: views($listing)
                ->period($window)
                ->remember(self::CacheMinutes)
                ->countByInterval(Granularity::Day),
            visitorsPerDay: views($listing)
                ->period($window)
                ->unique()
                ->remember(self::CacheMinutes)
                ->countByInterval(Granularity::Day),
            visitors: views($listing)
                ->period($window)
                ->unique()
                ->remember(self::CacheMinutes)
                ->count(),
            // The window against the 30 days before it, each count
            // remembered under its own key.
            trend: views($listing)
                ->period($window)
                ->remember(self::CacheMinutes)
                ->compare(),
            // Needs the `source` dimension in `dimensions.definitions`.
            sources: views($listing)
                ->period($window)
                ->remember(self::CacheMinutes)
                ->countBy('source', limit: self::TopSources),
        );
    }
}
