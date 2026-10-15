<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence;

/**
 * How the `redis` presence store counts visitors: every id in a sorted set,
 * or a HyperLogLog per minute that stays small however many there are.
 */
enum Precision: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
}
