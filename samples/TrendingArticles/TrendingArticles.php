<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Samples\TrendingArticles;

use CyrildeWit\EloquentViewable\Querying\Ranking\Ranking;
use CyrildeWit\EloquentViewable\Support\Period;

class TrendingArticles
{
    /**
     * How stale the ranking may be. `trending()` reads every view in the
     * window, so with the sidebar on every page the ranking is remembered
     * rather than read on each request.
     */
    private const int CacheMinutes = 10;

    private const int WindowDays = 7;

    /**
     * The articles trending over the past week, the one taking off fastest
     * first. Each entry carries the article, its views this week as `count`
     * and its views weighed by age as `score`.
     */
    public function get(int $limit = 10): Ranking
    {
        return views(Article::class)
            ->period(Period::pastDays(self::WindowDays))
            ->remember(self::CacheMinutes)
            ->trending($limit);
    }
}
