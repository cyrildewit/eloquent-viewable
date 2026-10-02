<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final readonly class ViewRecord
{
    public function __construct(
        public int|string $viewableId,
        public string $viewableType,
        public ?string $visitor,
        public ?string $collection,
        public CarbonInterface $viewedAt,
    ) {}

    /**
     * A store that keeps strings hands back a string key. The database coerces it.
     *
     * @param  array{viewable_id: int|string, viewable_type: string, visitor?: ?string, collection?: ?string, viewed_at: string}  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            viewableId: $payload['viewable_id'],
            viewableType: $payload['viewable_type'],
            visitor: $payload['visitor'] ?? null,
            collection: $payload['collection'] ?? null,
            viewedAt: Carbon::parse($payload['viewed_at']),
        );
    }

    /** @return array{viewable_id: int|string, viewable_type: string, visitor: ?string, collection: ?string, viewed_at: CarbonInterface} */
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

    /** @return array{viewable_id: int|string, viewable_type: string, visitor: ?string, collection: ?string, viewed_at: string} */
    public function toPayload(): array
    {
        return [
            'viewable_id' => $this->viewableId,
            'viewable_type' => $this->viewableType,
            'visitor' => $this->visitor,
            'collection' => $this->collection,
            'viewed_at' => $this->viewedAt->toIso8601String(),
        ];
    }
}
