<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking\Curves;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

/**
 * A view loses its weight evenly, down to nothing at the end of the window.
 */
final readonly class LinearDecay implements DecayCurve
{
    private float $window;

    /** @throws InvalidDecay */
    public function __construct(CarbonInterval $window)
    {
        $this->window = Seconds::positive($window, 'window');
    }

    public function weight(CarbonInterval $age): float
    {
        return max(0.0, 1 - max(0.0, $age->totalSeconds) / $this->window);
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
