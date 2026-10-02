<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Tests\Fixtures\Guards;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final class RefuseAll implements RecordingGuard
{
    public static int $calls = 0;

    public function allows(ViewAttempt $attempt): bool
    {
        self::$calls++;

        return false;
    }
}
