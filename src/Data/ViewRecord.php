<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;
use JsonException;

final readonly class ViewRecord
{
    /** @param  ?array<string, mixed>  $context */
    public function __construct(
        public int|string $viewableId,
        public string $viewableType,
        public ?string $visitor,
        public ?string $collection,
        public CarbonInterface $viewedAt,
        public ?string $viewerType = null,
        public int|string|null $viewerId = null,
        public ?array $context = null,
    ) {}

    /**
     * A store that keeps strings hands back string keys and the context as
     * JSON. The database coerces the keys.
     *
     * @param  array{viewable_id: int|string, viewable_type: string, visitor?: ?string, collection?: ?string, viewed_at: string, viewer_type?: ?string, viewer_id?: int|string|null, context?: array<string, mixed>|string|null}  $payload
     *
     * @throws JsonException
     */
    public static function fromPayload(array $payload): self
    {
        $context = $payload['context'] ?? null;

        if (is_string($context)) {
            /** @var array<string, mixed> $context */
            $context = json_decode($context, true, flags: JSON_THROW_ON_ERROR);
        }

        return new self(
            viewableId: $payload['viewable_id'],
            viewableType: $payload['viewable_type'],
            visitor: $payload['visitor'] ?? null,
            collection: $payload['collection'] ?? null,
            viewedAt: Carbon::parse($payload['viewed_at']),
            viewerType: $payload['viewer_type'] ?? null,
            viewerId: $payload['viewer_id'] ?? null,
            context: $context,
        );
    }

    /**
     * A viewable without a key matches every record of its type. Keys are
     * compared as strings, because a store may hand back a string key.
     */
    public function belongsTo(Viewable $viewable): bool
    {
        if ($this->viewableType !== $viewable->getMorphClass()) {
            return false;
        }

        $key = ViewableKey::of($viewable);

        return $key === null || (string) $this->viewableId === (string) $key;
    }

    /**
     * The context is encoded here because the stores write through the query
     * builder, which does not cast arrays.
     *
     * @return array{viewable_id: int|string, viewable_type: string, viewer_type: ?string, viewer_id: int|string|null, visitor: ?string, collection: ?string, context: ?string, viewed_at: CarbonInterface}
     *
     * @throws JsonException
     */
    public function toArray(): array
    {
        return [
            'viewable_id' => $this->viewableId,
            'viewable_type' => $this->viewableType,
            'viewer_type' => $this->viewerType,
            'viewer_id' => $this->viewerId,
            'visitor' => $this->visitor,
            'collection' => $this->collection,
            'context' => $this->encodedContext(),
            'viewed_at' => $this->viewedAt,
        ];
    }

    /**
     * @return array{viewable_id: int|string, viewable_type: string, viewer_type: ?string, viewer_id: int|string|null, visitor: ?string, collection: ?string, context: ?string, viewed_at: string}
     *
     * @throws JsonException
     */
    public function toPayload(): array
    {
        return [
            'viewable_id' => $this->viewableId,
            'viewable_type' => $this->viewableType,
            'viewer_type' => $this->viewerType,
            'viewer_id' => $this->viewerId,
            'visitor' => $this->visitor,
            'collection' => $this->collection,
            'context' => $this->encodedContext(),
            'viewed_at' => $this->viewedAt->toIso8601String(),
        ];
    }

    /** @throws JsonException */
    private function encodedContext(): ?string
    {
        return $this->context === null ? null : json_encode($this->context, JSON_THROW_ON_ERROR);
    }
}
