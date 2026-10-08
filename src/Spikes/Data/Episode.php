<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Data;

use Carbon\CarbonImmutable;

/**
 * An episode is open from the window a model left its baseline in until it
 * settles. It keeps the most extreme count and score it reached, and since
 * when it has been back to normal.
 *
 * @internal
 */
final readonly class Episode
{
    public function __construct(
        public int|string $key,
        public CarbonImmutable $since,
        public int $peakCount,
        public float $peakScore,
        public ?CarbonImmutable $quietSince,
    ) {}
}
