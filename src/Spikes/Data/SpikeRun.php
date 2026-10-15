<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Data;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * It holds how many models of one class started to spike, started to drop
 * and settled in one run of `views:detect-spikes`.
 */
final readonly class SpikeRun
{
    /** @param  class-string<Model&Viewable>  $class */
    public function __construct(
        public string $class,
        public int $spiked,
        public int $dropped,
        public int $settled,
    ) {}
}
