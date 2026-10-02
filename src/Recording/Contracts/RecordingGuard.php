<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

interface RecordingGuard
{
    public function allows(ViewAttempt $attempt): bool;
}
