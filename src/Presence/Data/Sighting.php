<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Presence\Data;

use Carbon\CarbonInterface;

/**
 * One moment a visitor was seen on a viewable. The visitor is a hash of their
 * visitor id, so presence never keeps the id itself. The viewer is only set
 * when `presence.viewers` is on.
 */
final readonly class Sighting
{
    public function __construct(
        public string $type,
        public int|string $key,
        public string $visitor,
        public CarbonInterface $seenAt,
        public ?string $collection = null,
        public ?Reference $viewer = null,
    ) {}

    public function viewable(): Reference
    {
        return new Reference($this->type, $this->key);
    }

    /**
     * The site, the type and the viewable, across every collection and, when
     * the visitor is in one, within it.
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        $scopes = [];

        foreach ($this->collections() as $collection) {
            $scopes[] = new Scope(collection: $collection);
            $scopes[] = new Scope($this->type, collection: $collection);
            $scopes[] = new Scope($this->type, $this->key, $collection);
        }

        return $scopes;
    }

    /**
     * Only the viewable, because the visitor may still be on another page of
     * the same type in another tab.
     *
     * @return list<Scope>
     */
    public function viewableScopes(): array
    {
        $scopes = [];

        foreach ($this->collections() as $collection) {
            $scopes[] = new Scope($this->type, $this->key, $collection);
        }

        return $scopes;
    }

    /** @return list<?string> */
    private function collections(): array
    {
        if ($this->collection === null) {
            return [null];
        }

        return [null, $this->collection];
    }
}
