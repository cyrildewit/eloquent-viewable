<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final readonly class IgnorePrefetch implements RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool
    {
        return ! $attempt->visitor->isPrefetch();
    }
}
