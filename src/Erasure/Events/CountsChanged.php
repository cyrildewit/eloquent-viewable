<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

/**
 * It is dispatched once an erasure changed the counts of the viewables it
 * names, keyed by their morph type. Null means the erasure touched more than
 * TouchedViewables::Limit viewables, so every count is forgotten and
 * recounted.
 *
 * @internal
 */
class CountsChanged
{
    /** @param  ?array<string, list<int|string>>  $viewables */
    public function __construct(public ?array $viewables) {}
}
