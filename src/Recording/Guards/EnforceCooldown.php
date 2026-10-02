<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Cooldowns\CooldownManager;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;

final readonly class EnforceCooldown implements RecordingGuard, RemembersRecordedViews
{
    public function __construct(private CooldownManager $cooldowns) {}

    public function allows(ViewAttempt $attempt): bool
    {
        if (! $attempt->cooldown instanceof CarbonInterface) {
            return true;
        }

        return ! $this->cooldowns->isActive($attempt->viewable, $attempt->collection);
    }

    public function remember(ViewAttempt $attempt): void
    {
        if ($attempt->cooldown instanceof CarbonInterface) {
            $this->cooldowns->start($attempt->viewable, $attempt->cooldown, $attempt->collection);
        }
    }
}
