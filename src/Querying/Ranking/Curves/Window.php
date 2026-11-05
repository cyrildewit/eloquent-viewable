<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking\Curves;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

/**
 * Every view inside the window weighs the same, and none outside it. The
 * ranking is then the one `top()` makes, with a score.
 */
final readonly class Window implements DecayCurve
{
    private float $window;

    /** @throws InvalidDecay */
    public function __construct(CarbonInterval $window)
    {
        $this->window = Seconds::positive($window, 'window');
    }

    public function weight(CarbonInterval $age): float
    {
        return $age->totalSeconds < $this->window ? 1.0 : 0.0;
    }

    public function horizon(): CarbonInterval
    {
        return CarbonInterval::seconds((int) ceil($this->window))->cascade();
    }

    public function identity(): string
    {
        return self::class.':'.$this->window;
    }
}
