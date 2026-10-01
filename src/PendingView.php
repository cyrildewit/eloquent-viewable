<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\CarbonInterface;

final readonly class PendingView
{
    public function __construct(
        public int|string $viewableId,
        public string $viewableType,
        public ?string $visitor,
        public ?string $collection,
        public CarbonInterface $viewedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'viewable_id' => $this->viewableId,
            'viewable_type' => $this->viewableType,
            'visitor' => $this->visitor,
            'collection' => $this->collection,
            'viewed_at' => $this->viewedAt,
        ];
    }
}
