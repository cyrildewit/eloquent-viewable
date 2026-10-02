<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Contracts;

use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

/**
 * Called once every guard has allowed the view and it has been stored or queued.
 */
interface RemembersRecordedViews
{
    public function remember(ViewAttempt $attempt): void;
}
