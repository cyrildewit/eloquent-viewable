<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Ranking;

use Countable;
use CyrildeWit\EloquentViewable\Contracts\Viewable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements Arrayable<int, array{rank: int, count: int, viewable: array<mixed>}>
 * @implements IteratorAggregate<int, Entry>
 */
final readonly class Ranking implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /** @param  Collection<int, Entry>  $entries */
    public function __construct(
        public Collection $entries,
    ) {}

    /** @return EloquentCollection<int, Model&Viewable> */
    public function viewables(): EloquentCollection
    {
        return new EloquentCollection($this->entries->map(static fn (Entry $entry): Model&Viewable => $entry->viewable)->all());
    }

    public function isEmpty(): bool
    {
        return $this->entries->isEmpty();
    }

    public function count(): int
    {
        return $this->entries->count();
    }

    /** @return list<array{rank: int, count: int, viewable: array<mixed>}> */
    public function toArray(): array
    {
        return array_values($this->entries->map(static fn (Entry $entry): array => [
            'rank' => $entry->rank,
            'count' => $entry->count,
            'viewable' => $entry->viewable->toArray(),
        ])->all());
    }

    /** @return list<array{rank: int, count: int, viewable: array<mixed>}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return Traversable<int, Entry> */
    public function getIterator(): Traversable
    {
        return $this->entries->getIterator();
    }
}
