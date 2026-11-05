<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Pairs\Events;

use Carbon\CarbonImmutable;

class ViewsPaired
{
    public function __construct(
        public CarbonImmutable $since,
        public int $viewables,
        public int $pairs,
    ) {}
}
