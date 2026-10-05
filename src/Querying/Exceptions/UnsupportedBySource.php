<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Querying\Contracts\CountsByDimension;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksAlsoViewed;
use CyrildeWit\EloquentViewable\Querying\Contracts\RanksTrending;
use CyrildeWit\EloquentViewable\Querying\Contracts\SubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\TrendingSubquerySource;
use CyrildeWit\EloquentViewable\Querying\Contracts\ViewSource;
use LogicException;

final class UnsupportedBySource extends LogicException implements EloquentViewableException
{
    public static function scopes(ViewSource $source): self
    {
        $class = $source::class;
        $contract = SubquerySource::class;

        return new self("The view source [{$class}] cannot be queried in SQL, so the withViewsCount(), orderByViews(), whereViewsCount() and whereViewedBy() scopes cannot read from it. Implement `{$contract}` on it, or count through views() instead.");
    }

    public static function counters(ViewSource $source): self
    {
        $class = $source::class;
        $contract = SubquerySource::class;

        return new self("The view source [{$class}] cannot be queried in SQL, so views:recount cannot write the counter columns from it. Implement `{$contract}` on it.");
    }

    public static function dimension(ViewSource $source): self
    {
        $class = $source::class;
        $contract = CountsByDimension::class;

        return new self("The view source [{$class}] cannot count by dimension, so countByDimension() cannot read from it. Implement `{$contract}` on it.");
    }

    public static function alsoViewed(ViewSource $source): self
    {
        $class = $source::class;
        $contract = RanksAlsoViewed::class;

        return new self("The view source [{$class}] cannot rank what visitors also viewed, so alsoViewed() cannot read from it. Implement `{$contract}` on it.");
    }

    public static function trending(ViewSource $source): self
    {
        $class = $source::class;
        $contract = RanksTrending::class;

        return new self("The view source [{$class}] cannot rank by trending, so trending() cannot read from it. Implement `{$contract}` on it.");
    }

    public static function trendingScopes(ViewSource $source): self
    {
        $class = $source::class;
        $contract = TrendingSubquerySource::class;

        return new self("The view source [{$class}] cannot weigh views by age in SQL, so the withTrendingScore() and orderByTrending() scopes cannot read from it. Implement `{$contract}` on it, or rank through views()->trending() instead.");
    }

    public static function filter(ViewSource $source): self
    {
        $class = $source::class;

        return new self("The view source [{$class}] cannot apply the filter of a rollup, so it cannot count through rollup(). Count through the database instead.");
    }
}
