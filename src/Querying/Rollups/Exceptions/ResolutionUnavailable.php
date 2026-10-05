<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Rollups\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use Exception;

final class ResolutionUnavailable extends Exception implements EloquentViewableException
{
    public static function partialBucket(): self
    {
        return new self('The period starts or ends inside a rollup bucket whose views are no longer kept, so the count cannot be exact. Align the period to the buckets, or turn `retention.rollups.strict` off to count a bucket when its start lies inside the period.');
    }

    public static function summedUniques(): self
    {
        return new self('Unique visitors over this period would be summed from more than one rollup bucket, which counts a visitor once per bucket. Turn `retention.rollups.strict` off to accept the sum.');
    }

    public static function otherTimezone(string $series, string $rollups): self
    {
        return new self("A series in `{$series}` cannot be built exactly from rollup buckets aligned to `{$rollups}`. Count by interval in `{$rollups}`, or turn `retention.rollups.strict` off to place a bucket by its start.");
    }

    public static function trendingStep(string $step): self
    {
        return new self("No rollup tier is as fine as the trending step of one {$step}, so the ranking cannot be read from the rollups. Keep an `{$step}` tier as long as the trending window, set `querying.trending.step` to match a tier, or turn `retention.rollups.strict` off to read the views table instead.");
    }
}
