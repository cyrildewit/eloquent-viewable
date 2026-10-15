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
    /**
     * @param  ?array<string, mixed>  $context
     * @param  array<string, ?string>  $dimensions  the values of the dimensions kept in a column, by column
     */
    public function __construct(
        public int|string $viewableId,
        public string $viewableType,
        public ?string $visitor,
        public ?string $collection,
        public CarbonInterface $viewedAt,
        public ?string $viewerType = null,
        public int|string|null $viewerId = null,
        public ?array $context = null,
        public array $dimensions = [],
    ) {}

    /**
     * A store that keeps strings hands back string keys, and the context and
     * dimensions as JSON. The database coerces the keys. A payload buffered
     * before dimensions were recorded has none.
     *
     * @param  array{viewable_id: int|string, viewable_type: string, visitor?: ?string, collection?: ?string, viewed_at: string, viewer_type?: ?string, viewer_id?: int|string|null, context?: array<string, mixed>|string|null, dimensions?: array<string, ?string>|string|null}  $payload
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

        $dimensions = $payload['dimensions'] ?? [];

        if (is_string($dimensions)) {
            /** @var array<string, ?string> $dimensions */
            $dimensions = json_decode($dimensions, true, flags: JSON_THROW_ON_ERROR);
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
            dimensions: $dimensions,
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
     * builder, which does not cast arrays. Each dimension is a column of its
     * own.
     *
     * @return array<string, mixed>
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
            ...$this->dimensions,
        ];
    }

    /**
     * The dimensions travel as one key, so a stream entry only gains a field.
     *
     * @return array{viewable_id: int|string, viewable_type: string, viewer_type: ?string, viewer_id: int|string|null, visitor: ?string, collection: ?string, context: ?string, viewed_at: string, dimensions: ?string}
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
            'dimensions' => $this->dimensions === [] ? null : json_encode($this->dimensions, JSON_THROW_ON_ERROR),
        ];
    }

    /** @throws JsonException */
    private function encodedContext(): ?string
    {
        return $this->context === null ? null : json_encode($this->context, JSON_THROW_ON_ERROR);
    }
}
