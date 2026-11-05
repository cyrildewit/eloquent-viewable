<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Doctor\Data;

class Finding
{
    public function __construct(
        public Status $status,
        public string $summary,
        public ?string $fix = null,
    ) {}

    public static function pass(string $summary): self
    {
        return new self(Status::Pass, $summary);
    }

    public static function advice(string $summary, ?string $fix = null): self
    {
        return new self(Status::Advice, $summary, $fix);
    }

    public static function warning(string $summary, ?string $fix = null): self
    {
        return new self(Status::Warning, $summary, $fix);
    }

    public static function failure(string $summary, ?string $fix = null): self
    {
        return new self(Status::Failure, $summary, $fix);
    }

    public static function skipped(string $summary): self
    {
        return new self(Status::Skipped, $summary);
    }

    /** @return array{status: string, summary: string, fix: ?string} */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'summary' => $this->summary,
            'fix' => $this->fix,
        ];
    }
}
