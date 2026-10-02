<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;

/**
 * What became of a call to record a view: it was stored, it was queued, or a
 * guard refused it. When a guard refused, `skippedBy` holds that guard.
 */
final readonly class RecordResult
{
    private function __construct(
        public bool $recorded,
        public bool $queued,
        public ?RecordingGuard $skippedBy,
    ) {}

    public static function stored(): self
    {
        return new self(recorded: true, queued: false, skippedBy: null);
    }

    public static function queued(): self
    {
        return new self(recorded: true, queued: true, skippedBy: null);
    }

    public static function skipped(RecordingGuard $guard): self
    {
        return new self(recorded: false, queued: false, skippedBy: $guard);
    }

    /** @param  class-string<RecordingGuard>  $guard */
    public function wasSkippedBy(string $guard): bool
    {
        return $this->skippedBy instanceof $guard;
    }
}
