<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final readonly class ViewSkipped
{
    public function __construct(
        public ViewAttempt $attempt,
        public RecordingGuard $guard,
    ) {}
}
