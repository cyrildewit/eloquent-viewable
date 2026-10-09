<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Exceptions;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Exceptions\EloquentViewableException;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;
use InvalidArgumentException;

final class InvalidDecay extends InvalidArgumentException implements EloquentViewableException
{
    public static function producesTooManySteps(int $steps, int $maximum): self
    {
        return new self("The trending window would be weighed in {$steps} steps, more than the maximum of {$maximum}. Narrow the period, weigh per day, or raise `querying.trending.max_steps`.");
    }

    public static function weightOutOfRange(DecayCurve $curve, float $weight): self
    {
        $class = $curve::class;
        $given = is_nan($weight) ? 'NAN' : (string) $weight;

        return new self("The trending curve [{$class}] returned a weight of {$given}. A weight must be from 0 to 1.");
    }

    public static function halfLifeAndCurve(): self
    {
        return new self('Pass either a half-life or a curve, not both. The half-life is a setting of `ExponentialDecay`, so pass `new ExponentialDecay($halfLife)` as the curve instead.');
    }

    public static function mustBePositive(string $name, CarbonInterval $interval): self
    {
        return new self("The {$name} of a trending curve must be longer than zero, {$interval->forHumans()} given.");
    }
}
