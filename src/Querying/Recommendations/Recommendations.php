<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Querying\Recommendations;

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
 * @implements Arrayable<int, array{rank: int, score: float, viewable: array<mixed>, because: list<array<mixed>>}>
 * @implements IteratorAggregate<int, Recommendation>
 */
final readonly class Recommendations implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /** @param  Collection<int, Recommendation>  $entries */
    public function __construct(
        public Collection $entries,
    ) {}

    /** @return EloquentCollection<int, Model&Viewable> */
    public function viewables(): EloquentCollection
    {
        return new EloquentCollection($this->entries->map(static fn (Recommendation $recommendation): Model&Viewable => $recommendation->viewable)->all());
    }

    public function isEmpty(): bool
    {
        return $this->entries->isEmpty();
    }

    public function count(): int
    {
        return $this->entries->count();
    }

    /** @return list<array{rank: int, score: float, viewable: array<mixed>, because: list<array<mixed>>}> */
    public function toArray(): array
    {
        return array_values($this->entries->map(static fn (Recommendation $recommendation): array => [
            'rank' => $recommendation->rank,
            'score' => $recommendation->score,
            'viewable' => $recommendation->viewable->toArray(),
            'because' => array_values($recommendation->because->map(static fn (Model $model): array => $model->toArray())->all()),
        ])->all());
    }

    /** @return list<array{rank: int, score: float, viewable: array<mixed>, because: list<array<mixed>>}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return Traversable<int, Recommendation> */
    public function getIterator(): Traversable
    {
        return $this->entries->getIterator();
    }
}
