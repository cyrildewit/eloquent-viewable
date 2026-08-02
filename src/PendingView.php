<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable;

use Carbon\CarbonInterface;

/**
 * A value object describing a view that has been resolved during the request
 * but not yet stored. It carries only serializable scalars so that it can be
 * safely dispatched onto a queue and later handed to the CreateView action.
 */
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
