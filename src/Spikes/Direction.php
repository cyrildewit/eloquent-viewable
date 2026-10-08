<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes;

/**
 * Which way a model left its baseline: far above it, or far below it.
 */
enum Direction: string
{
    case Spike = 'spike';

    case Drop = 'drop';
}
