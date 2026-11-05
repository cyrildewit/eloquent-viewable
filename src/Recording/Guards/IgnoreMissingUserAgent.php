<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Drops views from requests without a user agent. Browsers always send one;
 * scripts and health checks often do not, and the crawler detector lets them
 * through.
 */
final readonly class IgnoreMissingUserAgent implements RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool
    {
        return $attempt->visitor->userAgent() !== null;
    }
}
