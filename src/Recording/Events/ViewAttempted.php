<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Dispatched once for every attempt the guards have judged, with what came of
 * it: stored, queued or skipped. It fires in the request that made the attempt,
 * also when the write is queued, so a listener sees every view of the request
 * without waiting for a worker. Only dispatched when something listens.
 */
class ViewAttempted
{
    public function __construct(
        public ViewAttempt $attempt,
        public RecordResult $result,
    ) {}
}
