<?php

declare(strict_types=1);

namespace CyrildeWit\EloquentViewable\Erasure\Events;

/** @internal */
class CountsChanged
{
    /** @param  ?array<string, list<int|string>>  $viewables */
    public function __construct(public ?array $viewables) {}
}
