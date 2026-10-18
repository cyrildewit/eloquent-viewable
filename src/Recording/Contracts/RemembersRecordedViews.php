<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * A guard that implements this is called once every guard has allowed the
 * view and it has been stored or queued. It can then start a cooldown or
 * count a hit only for views that count.
 */
interface RemembersRecordedViews
{
    public function remember(ViewAttempt $attempt): void;
}
