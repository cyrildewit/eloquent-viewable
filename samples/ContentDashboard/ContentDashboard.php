<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ContentDashboard;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidPeriod;
use CyrildeWit\EloquentViewable\Facades\Views;
use CyrildeWit\EloquentViewable\Querying\Comparison\ViewComparison;
use CyrildeWit\EloquentViewable\Support\Period;
use CyrildeWit\EloquentViewable\Views as ViewsBuilder;

class ContentDashboard
{
    /**
     * The editors work in Amsterdam while the application runs in UTC, so
     * "the past 7 days" starts at midnight on their clock.
     */
    private const string Timezone = 'Europe/Amsterdam';

    private const int CacheMinutes = 10;

    private const int TopLimit = 10;

    private const int LatestGuides = 20;

    public function for(Period $period): DashboardReport
    {
        $latestGuides = Guide::query()->latest('id')->limit(self::LatestGuides)->get();

        return new DashboardReport(
            period: $period,
            // Guides and episodes ranked together, one query for the counts
            // and one per type for the models.
            top: $this->query($period)->top(self::TopLimit),
            trends: [
                'guides' => $this->trend(new Guide, $period),
                'episodes' => $this->trend(new Episode, $period),
            ],
            placements: $this->placements($period),
            latestGuides: $latestGuides,
            // The counts of the guides already loaded, in one query instead
            // of one per row.
            latestGuideViews: $this->query($period)->forViewables($latestGuides)->counts()->all(),
        );
    }

    /**
     * Every count of the dashboard reads through this builder, so each one
     * uses the editors' clock and is remembered for ten minutes.
     */
    private function query(Period $period): ViewsBuilder
    {
        return Views::period($period)
            ->timezone(self::Timezone)
            ->remember(self::CacheMinutes);
    }

    /**
     * Null for a period that is open on one side, such as `2026-09-01..`,
     * which has no width to step back by.
     */
    private function trend(Viewable $type, Period $period): ?ViewComparison
    {
        try {
            return $this->query($period)->forViewable($type)->compare();
        } catch (InvalidPeriod) {
            return null;
        }
    }

    /**
     * Views per placement across both types, most viewed first.
     *
     * @return array<string, int>
     */
    private function placements(Period $period): array
    {
        $placements = [];

        foreach ([new Guide, new Episode] as $type) {
            foreach ($this->query($period)->forViewable($type)->countByCollection() as $collection => $count) {
                // Views recorded without a collection come back under ''.
                $name = $collection === '' ? 'direct' : $collection;

                $placements[$name] = ($placements[$name] ?? 0) + $count;
            }
        }

        arsort($placements);

        return $placements;
    }
}
