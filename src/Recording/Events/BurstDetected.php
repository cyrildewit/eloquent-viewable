<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final readonly class BurstDetected
{
    /** @param  'visitor'|'network'  $by */
    public function __construct(
        public ViewAttempt $attempt,
        public string $by,
    ) {}
}
