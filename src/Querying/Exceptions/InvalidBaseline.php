<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use InvalidArgumentException;

final class InvalidBaseline extends InvalidArgumentException implements EloquentViewableException
{
    public static function samplesBelowOne(int $samples): self
    {
        return new self("A baseline needs at least one sample, {$samples} given.");
    }

    public static function minimumBelowOne(int $minimum, string $method): self
    {
        return new self("{$method} needs a minimum of at least one view, {$minimum} given.");
    }

    public static function thresholdOfZero(): self
    {
        return new self('anomalies() needs a threshold above 0 for spikes or below 0 for drops, 0 given.');
    }

    public static function withoutStart(): self
    {
        return new self('A baseline needs a period with a start. Call `period()` first, with a period such as `Period::subHours(1)`.');
    }

    public static function windowLongerThanSeason(string $seasonality): self
    {
        return new self("The period is longer than a {$seasonality}, so the windows it is compared with would overlap. Pick a shorter period or a longer seasonality.");
    }
}
