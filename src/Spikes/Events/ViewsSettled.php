<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Events;

use Carbon\CarbonImmutable;
use CyrildeWit\EloquentViewable\Spikes\Direction;
use CyrildeWit\EloquentViewable\Support\Concerns\NamesViewable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

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
