<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Spikes\Direction;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * It is dispatched once a model that spiked or dropped has stayed back to
 * normal for the cooldown, with the most extreme count and score it reached.
 */
class ViewsSettled implements ShouldDispatchAfterCommit
{
    use NamesViewable;

    /**
     * @param  string  $type  the morph type of the model
     * @param  int|string  $key  the key of the model
     * @param  Direction  $direction  whether it spiked or dropped
     * @param  int  $peakCount  the most extreme count of a window during the episode
     * @param  float  $peakScore  the most extreme z-score during the episode
     * @param  CarbonImmutable  $since  the end of the window the episode started in
     */
    public function __construct(
        public string $type,
        public int|string $key,
        public Direction $direction,
        public int $peakCount,
        public float $peakScore,
        public CarbonImmutable $since,
    ) {}
}
