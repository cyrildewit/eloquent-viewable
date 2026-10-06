<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use JsonSerializable;

/**
 * Present is true when the attempt kept the visitor active for the live
 * counts, which a view skipped by a cooldown or the throttle still does.
 */
final readonly class RecordResult implements JsonSerializable
{
    private function __construct(
        public bool $recorded,
        public bool $queued,
        public ?RecordingGuard $skippedBy,
        public bool $present = false,
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

    public function withPresence(bool $present): self
    {
        return new self($this->recorded, $this->queued, $this->skippedBy, $present);
    }

    /** @param  class-string<RecordingGuard>  $guard */
    public function wasSkippedBy(string $guard): bool
    {
        return $this->skippedBy instanceof $guard;
    }

    /**
     * Names the guard by its class, so a log line or a Telescope entry shows
     * which guard skipped the view.
     *
     * @return array{recorded: bool, queued: bool, skipped_by: ?class-string<RecordingGuard>, present: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'recorded' => $this->recorded,
            'queued' => $this->queued,
            'skipped_by' => $this->skippedBy instanceof RecordingGuard
                ? $this->skippedBy::class
                : null,
            'present' => $this->present,
        ];
    }
}
