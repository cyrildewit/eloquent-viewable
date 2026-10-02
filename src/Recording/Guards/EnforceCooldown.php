<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Guards;

use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Cooldowns\Contracts\CooldownStore;
use CyrildeWit\EloquentViewable\Cooldowns\Cooldown;
use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use CyrildeWit\EloquentViewable\Recording\Contracts\RemembersRecordedViews;
use CyrildeWit\EloquentViewable\Recording\Data\ViewAttempt;
use CyrildeWit\EloquentViewable\Visitors\VisitorIdentity;

final readonly class EnforceCooldown implements RecordingGuard, RemembersRecordedViews
{
    public function __construct(
        private CooldownStore $cooldowns,
        private VisitorIdentity $identity,
    ) {}

    public function allows(ViewAttempt $attempt): bool
    {
        if (! $attempt->cooldown instanceof CarbonInterface) {
            return true;
        }

        return ! $this->cooldowns->has($this->key($attempt));
    }

    public function remember(ViewAttempt $attempt): void
    {
        if ($attempt->cooldown instanceof CarbonInterface) {
            $this->cooldowns->put($this->key($attempt), $attempt->cooldown);
        }
    }

    private function key(ViewAttempt $attempt): string
    {
        return Cooldown::of($attempt->viewable, $this->identity->of($attempt->visitor, $attempt->viewer), $attempt->collection)->key();
    }
}
