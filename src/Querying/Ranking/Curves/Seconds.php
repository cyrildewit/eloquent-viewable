<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking\Curves;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;

/** @internal */
final readonly class Seconds
{
    /**
     * The length of the interval in seconds, which a curve divides by.
     *
     * @throws InvalidDecay
     */
    public static function positive(CarbonInterval $interval, string $name): float
    {
        $seconds = $interval->totalSeconds;

        if ($seconds <= 0) {
            throw InvalidDecay::mustBePositive($name, $interval);
        }

        return $seconds;
    }
}
