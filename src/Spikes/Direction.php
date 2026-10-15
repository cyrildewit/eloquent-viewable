<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Spikes;

/**
 * A direction says which way a model left its baseline: far above it, or far
 * below it.
 */
enum Direction: string
{
    case Spike = 'spike';

    case Drop = 'drop';
}
