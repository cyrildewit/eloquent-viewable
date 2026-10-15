<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Data;

use CyrildeWit\EloquentViewable\Contracts\Viewable;
use CyrildeWit\EloquentViewable\Exceptions\InvalidViewable;
use CyrildeWit\EloquentViewable\Support\ViewableKey;

/**
 * What a live count is read over: the whole site, one type or one viewable,
 * across every collection or within one.
 */
final readonly class Scope
{
    public function __construct(
        public ?string $type = null,
        public int|string|null $key = null,
        public ?string $collection = null,
    ) {}

    /**
     * A viewable without a key, such as `new Post`, stands for its type.
     *
     * @throws InvalidViewable
     */
    public static function of(?Viewable $viewable, ?string $collection = null): self
    {
        if (! $viewable instanceof Viewable) {
            return new self(collection: $collection);
        }

        return new self($viewable->getMorphClass(), ViewableKey::of($viewable), $collection);
    }

    /**
     * Every part is URL encoded, which leaves no `|` or `*` in it, and `*`
     * stands for a part that is not set, so no two scopes share an id.
     */
    public function id(): string
    {
        return implode('|', [
            $this->encode($this->type),
            $this->encode($this->key),
            $this->encode($this->collection),
        ]);
    }

    private function encode(int|string|null $part): string
    {
        if ($part === null) {
            return '*';
        }

        return rawurlencode((string) $part);
    }
}
