<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Events;

use Carbon\CarbonImmutable;

/**
 * It is dispatched once `views:pairs` rewrote the pairs table from the views
 * since `since`: the pairs of `viewables` viewables, `pairs` rows in all.
 */
class ViewsPaired
{
    public function __construct(
        public CarbonImmutable $since,
        public int $viewables,
        public int $pairs,
    ) {}
}
