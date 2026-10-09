<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Spikes\Direction;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once a model that spiked or dropped has stayed back to
 * normal for the cooldown. It holds the most extreme count and z-score of a
 * window during the episode, and the end of the window it started in.
 */
class ViewsSettled implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    public function __construct(
        public string $type,
        public int|string $key,
        public Direction $direction,
        public int $peakCount,
        public float $peakScore,
        public CarbonImmutable $since,
    ) {}
}
