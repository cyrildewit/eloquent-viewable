<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking\Curves;

use Carbon\CarbonInterval;
use CyrildeWit\EloquentViewable\Querying\Exceptions\InvalidDecay;
use CyrildeWit\EloquentViewable\Querying\Ranking\DecayCurve;

/**
 * A view loses half its weight every half-life, so it never drops out
 * abruptly. Past eight half-lives it weighs less than 0.4%, and is left out.
 */
final readonly class ExponentialDecay implements DecayCurve
{
    private const int HalfLives = 8;

    private float $halfLife;

    /** @throws InvalidDecay */
    public function __construct(CarbonInterval $halfLife)
    {
        $this->halfLife = Seconds::positive($halfLife, 'half-life');
    }

    public function weight(CarbonInterval $age): float
    {
        return 0.5 ** (max(0.0, $age->totalSeconds) / $this->halfLife);
    }

    public function horizon(): CarbonInterval
    {
        return CarbonInterval::seconds((int) ceil($this->halfLife * self::HalfLives))->cascade();
    }

    public function identity(): string
    {
        return self::class.':'.$this->halfLife;
    }
}
