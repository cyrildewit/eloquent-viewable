<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes\Data;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Database\Eloquent\Model;

/**
 * What one run of `views:detect-spikes` found for one model class.
 */
final readonly class SpikeRun
{
    /**
     * @param  class-string<Model&Viewable>  $class
     * @param  int  $spiked  the models that started to spike
     * @param  int  $dropped  the models that started to drop
     * @param  int  $settled  the models that settled
     */
    public function __construct(
        public string $class,
        public int $spiked,
        public int $dropped,
        public int $settled,
    ) {}
}
