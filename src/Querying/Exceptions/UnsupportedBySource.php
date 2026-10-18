<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsBy;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByWindow;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsVisitFrequency;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksRecommendations;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions\ResolutionUnavailable;
use LogicException;

final class UnsupportedBySource extends LogicException implements EloquentViewableException
{
    /**
     * The source lacks one of the contracts a read needs. The message says
     * what the source cannot do and which reads that keeps from it.
     *
     * @param  class-string  $contract
     */
    public static function missing(string $contract, ViewSource $source): self
    {
        $words = match ($contract) {
            SubquerySource::class => ['be queried in SQL', 'the withViewsCount(), orderByViews(), whereViewsCount() and whereViewedBy() scopes', ', or count through views() instead'],
            TrendingSubquerySource::class => ['weigh views by age in SQL', 'the withTrendingScore() and orderByTrending() scopes', ', or rank through views()->trending() instead'],
            CountsByDimension::class => ['count by dimension', 'countByDimension()', ''],
            CountsBy::class => ['count by a dimension', 'countBy()', ''],
            RanksAlsoViewed::class => ['rank what visitors also viewed', 'alsoViewed()', ''],
            CountsVisitFrequency::class => ['count how often visitors came back', 'returning() and countByFrequency()', ''],
            RanksRecommendations::class => ['read what recommendations are made from', 'recommended() and recommendedFor()', ''],
            RanksTrending::class => ['rank by trending', 'trending()', ''],
            CountsByWindow::class => ['count by window', 'rising(), anomalies() and views:detect-spikes', ''],
            default => null,
        };

        if ($words === null) {
            $class = $source::class;

            return new self("The view source [{$class}] does not implement `{$contract}`, so what needs it cannot read from it.");
        }

        [$what, $reads, $alternative] = $words;

        return self::cannot($source, $what, "{$reads} cannot read from it", $contract, $alternative);
    }

    public static function counters(ViewSource $source): self
    {
        return self::cannot($source, 'be queried in SQL', 'views:recount cannot write the counter columns from it', SubquerySource::class);
    }

    /** @param  non-empty-list<string>  $dimensions */
    public static function dimensionHistory(array $dimensions, CarbonInterface $pruned): self
    {
        return new self(ResolutionUnavailable::dimensionHistoryMessage($dimensions, $pruned));
    }

    public static function filter(ViewSource $source): self
    {
        $class = $source::class;

        return new self("The view source [{$class}] cannot apply the filter of a rollup, so it cannot count through rollup(). Count through the database instead.");
    }

    private static function cannot(ViewSource $source, string $what, string $consequence, string $contract, string $alternative = ''): self
    {
        $class = $source::class;

        return new self("The view source [{$class}] cannot {$what}, so {$consequence}. Implement `{$contract}` on it{$alternative}.");
    }
}
