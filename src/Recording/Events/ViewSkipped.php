<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Events;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Dispatched when a guard refuses an attempt, with the guard that did. The
 * attempt holds the viewable, the visitor and the options the call asked for,
 * so a listener can log why a count stays where it is.
 */
final readonly class ViewSkipped
{
    public function __construct(
        public ViewAttempt $attempt,
        public RecordingGuard $guard,
    ) {}
}
