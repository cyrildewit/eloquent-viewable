<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Drops views from `HEAD` requests. Laravel answers them with the `GET`
 * route, so link checkers and uptime monitors would otherwise run the
 * controller that records the view.
 */
final readonly class IgnoreHeadRequests implements RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool
    {
        return ! $attempt->visitor->isHeadRequest();
    }
}
