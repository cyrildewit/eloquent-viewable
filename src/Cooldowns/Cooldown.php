<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Cooldowns;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;

final readonly class Cooldown
{
    private function __construct(
        public string $viewableType,
        public int|string|null $viewableId,
        public string $visitorId,
        public ?string $collection,
    ) {}

    public static function of(Viewable $viewable, string $visitorId, ?string $collection = null): self
    {
        return new self($viewable->getMorphClass(), ViewableKey::of($viewable), $visitorId, $collection);
    }

    /**
     * The parts are JSON encoded so that no part can run into the next, and
     * hashed so that the key fits the key length limit of any cache store.
     */
    public function key(): string
    {
        return hash('xxh128', json_encode(
            [$this->viewableType, (string) $this->viewableId, $this->collection, $this->visitorId],
            JSON_THROW_ON_ERROR,
        ));
    }
}
