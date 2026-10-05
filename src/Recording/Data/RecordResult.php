<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Recording\Data;

use CyrildeWit\EloquentViewable\Recording\Contracts\RecordingGuard;
use JsonSerializable;

final readonly class RecordResult implements JsonSerializable
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

    /**
     * The guard by its class, so a log line or a Telescope entry says which
     * guard skipped the view rather than an empty object.
     *
     * @return array{recorded: bool, queued: bool, skipped_by: ?class-string<RecordingGuard>}
     */
    public function jsonSerialize(): array
    {
        return [
            'recorded' => $this->recorded,
            'queued' => $this->queued,
            'skipped_by' => $this->skippedBy instanceof RecordingGuard ? $this->skippedBy::class : null,
        ];
    }
}
