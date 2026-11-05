<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Data\RecordResult;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

class ViewAttempted
{
    public function __construct(
        public ViewAttempt $attempt,
        public RecordResult $result,
    ) {}
}
