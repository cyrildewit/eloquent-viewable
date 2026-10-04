<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\ContentDashboard;

use CyrildeWit\EloquentViewable\Support\Period;

class ShowDashboard
{
    /**
     * `/dashboard/7d`, `/dashboard/3m` or `/dashboard/2026-09-01..2026-10-01`.
     * The router parses the segment into a `Period`, and answers a 404 when
     * it cannot.
     */
    public function __invoke(Period $period, ContentDashboard $dashboard): DashboardReport
    {
        return $dashboard->for($period);
    }
}
