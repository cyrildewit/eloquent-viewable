<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Carbon\CarbonInterval;

/**
 * A curve decides how much a view of a given age is worth to a trending
 * ranking. It is plain PHP: `Decay` asks it for one weight per step, so every
 * source, rollups and the cache work with a curve of your own unchanged.
 */
interface DecayCurve
{
    /**
     * The weight of a view of this age, from 0 to 1.
     */
    public function weight(CarbonInterval $age): float;

    /**
     * The age past which a view no longer counts. It's the window when the
     * period has no start.
     */
    public function horizon(): CarbonInterval;

    /**
     * The class and its parameters, which keep two curves apart in the cache.
     */
    public function identity(): string;
}
