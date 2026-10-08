<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Contracts\ViewerCanOptOut;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * The signed-in user is asked even when `recording.viewer.enabled` is off,
 * because the visitor cookie still ties their views together.
 */
final readonly class IgnoreOptedOutViewers implements RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool
    {
        $viewer = $attempt->viewer ?? $attempt->visitor->viewer();

        return ! $viewer instanceof ViewerCanOptOut || $viewer->tracksViews();
    }
}
